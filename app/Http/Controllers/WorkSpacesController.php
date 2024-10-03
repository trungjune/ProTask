<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RedirectIfNotAdmin;
use App\Models\Assignee;
use App\Models\Attachment;
use App\Models\BoardList;
use App\Models\CheckList;
use App\Models\Comment;
use App\Models\Project;
use App\Models\RecentProject;
use App\Models\StarredProject;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Models\TeamMember;
use App\Models\Timer;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class WorkSpacesController extends Controller
{
    //
    public function index(){
        $user_id = auth()->id();
        $workspaceIds = Workspace::where('user_id', $user_id)->orWhereHas('member')->pluck('id');
        $project = RecentProject::where('user_id', $user_id)->with('project')->has('project.workspace')->whereHas('project', function ($q) use ($workspaceIds) {
            $q->whereIn('workspace_id', $workspaceIds);
        })->orderBy('opened', 'desc')->first();
        if(!empty($project)){
            return Redirect::route('projects.view.board', $project->project->slug?:$project->project->id);
        }
        $project = Project::whereIn('workspace_id', $workspaceIds)->orderBy('updated_at', 'desc')->first();
        if(!empty($project)){
            return Redirect::route('projects.view.board', $project->slug?:$project->id);
        }
        $assignee = Assignee::where('user_id', $user_id)->whereHas('task')->with('task')->first();
        if(!empty($assignee)){
            return Redirect::route('projects.view.board', ['uid' => $assignee->task->project_id, 'task' => $assignee->task->id]);
        }
        return Redirect::route('projects.view.na');
    }
    public function jsonAll(){
        $user_id = auth()->id();
        $workSpaces = Workspace::where('user_id', $user_id)->orWhereHas('member')->with('member')->get()->toArray();
        return response()->json($workSpaces);
    }

    public function jsonMineAll(){
        $myWorkspaces = Workspace::where('user_id', auth()->id())->limit(50)->get()->toArray();
        return response()->json($myWorkspaces);
    }


    public function jsonCreate(Request $request){
        $requests = $request->all();
        $requests['user_id'] = auth()->id();
        $workspace = Workspace::create($requests);

        $slug = $this->clean($workspace->name);
        $existingItem = Workspace::where('slug', $slug)->first();
        if(!empty($existingItem)){
            $slug = $slug . '-' . $workspace->id;
        }
        $workspace->slug = $slug;
        $workspace->save();

        TeamMember::create(['workspace_id' => $workspace->id, 'user_id' => $requests['user_id'], 'role' => 'admin', 'added_by' => $requests['user_id']]);

        return response()->json($workspace);
    }

    public function jsonChangeWorkspace(Request $request){
        $requestData = $request->all();
        $project = Project::where('id', $requestData['project_id'])->first();
        $project->workspace_id = $requestData['workspace_id'];
        $project->save();
        return response()->json($project);
    }

    public function jsonUpdateWorkspace($id, Request $request){
        $requestData = $request->all();
        $workspace = Workspace::where('id', $id)->first();
        foreach ($requestData as $itemKey => $itemValue){
            $workspace->{$itemKey} = $itemValue;
        }
        $workspace->save();
        return response()->json($workspace);
    }

    public function jsonAddMember(Request $request){
        $requestData = $request->all();
        $teamMember = TeamMember::where(['workspace_id' => $requestData['workspace_id'], 'user_id' => $requestData['user_id']])->first();
        if(!empty($teamMember)){
            $teamMember->delete();
            $teamMember = ['success' => true ];
        }else{
            $requestData['added_by'] = auth()->id();
            $teamMember = TeamMember::create($requestData);
            $teamMember->load('user');
        }
        return response()->json($teamMember);
    }

    public function workspaceView($uid){
        $workspace = Workspace::whereId($uid)->orWhere('slug', $uid)->whereHas('member')->with('member')->first();
        $projects = Project::where('workspace_id', $workspace->id)->with('star')->with('background')->get();
        return Inertia::render('Workspaces/View', [
            'title' => 'Projects | '.$workspace->name,
            'workspace' => $workspace,
            'projects' => $projects
        ]);
    }

    public function workspaceMembers($uid, Request $request){
        $workspace = Workspace::whereId($uid)->orWhere('slug', $uid)->whereHas('member')->with('member')->first();
        if($workspace->member->role != 'admin'){
                return Redirect::route('workspace.view', $workspace->id);
        }
        $projects = Project::where('workspace_id', $workspace->id)->with('star')->with('background')->get();
        return Inertia::render('Workspaces/Members', [
            'title' => 'Members | '.$workspace->name,
            'workspace' => $workspace,
            'projects' => $projects,
            'team_members' => TeamMember::where('workspace_id', $workspace->id)
                ->filter($request->only('search'))
                ->orderBy('created_at', 'DESC')
                ->paginate(10)
                ->withQueryString()
                ->through(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->user->first_name.' '.$member->user->last_name,
                        'photo' => $member->user->photo_path,
                        'role' => $member->role,
                        'workspace_id' => $member->workspace_id,
                        'user_id' => $member->user_id,
                        'created_at' => $member->created_at,
                    ];
                } ),
        ]);
    }

    public function workspaceTables($uid, Request $request){
        $user = auth()->user()->load('role');
        $requests = $request->all();
        if(!empty($user->role)){
            if($user->role->slug != 'admin' && empty($requests['user'])){
                return Redirect::route('workspace.tables', ['uid' => $uid, 'user' => $user->id]);
            }
        }else{
            return abort(404);
        }

        $list_index = [];
        $board_lists = BoardList::orderByOrder()->get();
        $workspace = Workspace::where('id', $uid)->orWhere('slug', $uid)->whereHas('member')->with('member')->first();
        $loopIndex = 0;
        foreach ($board_lists as &$listItem){
            $list_index[$listItem->id] = $loopIndex;
            $listItem['tasks'] = [];
            $loopIndex+= 1;
        }
        return Inertia::render('Workspaces/Table', [
            'title' => 'Tasks | '.$workspace->name,
            'board_lists' => $board_lists,
            'filters' => $requests,
            'list_index' => $list_index,
            'workspace' => $workspace,
            'tasks' => Task::filter($requests)->whereHas('project', function ($q) use ($workspace) {
                $q->where('workspace_id', $workspace->id);
            })->with('list')->with('taskLabels.label')->with('project.background')->with('assignees')->with('timer')->isOpen()->orderByOrder()->get()
        ]);
    }

    public function getOtherUsers($workspace_id){
        $workspaceUsers = TeamMember::where('workspace_id', $workspace_id)->groupBy('user_id')->pluck('user_id');
        $users = User::select('id', 'first_name', 'last_name', 'photo_path')->where('id', '!=', auth()->id())->get();
        return response()->json(['users' => $users, 'workspace_users' => $workspaceUsers]);
    }

    private function clean($string) {
        $string = str_replace(' ', '-', $string);
        $string = preg_match("/[a-z]/i", $string)?$string:'untitled';
        $string = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
        return preg_replace('/-+/', '-', $string);
    }

    public function destroy($id){
        $workspace = Workspace::where('id', $id)->first();
        $workspace->delete();
        TeamMember::where('workspace_id', $id)->delete();
        $projects = Project::where('workspace_id', $id)->get();
        foreach ($projects as $project){
            BoardList::where('project_id', $project->id)->delete();
            RecentProject::where('project_id', $project->id)->delete();
            StarredProject::where('project_id', $project->id)->delete();
            $tasks = Task::where('project_id', $project->id)->get();
            foreach ($tasks as $task){
                $attachments = Attachment::where('task_id', $task->id)->get();
                foreach ($attachments as $attachment){
                    if(!empty($attachment->path) && File::exists(public_path($attachment->path))){
                        File::delete(public_path($attachment->path));
                    }
                    $attachment->delete();
                }
                CheckList::where('task_id', $task->id)->delete();
                Timer::where('task_id', $task->id)->delete();
                Comment::where('task_id', $task->id)->delete();
                Assignee::where('task_id', $task->id)->delete();
                TaskLabel::where('task_id', $task->id)->delete();
                $task->delete();
            }
            $project->delete();
        }
        return Redirect::route('dashboard');
    }
}
