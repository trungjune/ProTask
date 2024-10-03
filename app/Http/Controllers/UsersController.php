<?php

namespace App\Http\Controllers;

use App\Events\UserCreated;
use App\Http\Middleware\RedirectIfNotAdmin;
use App\Http\Middleware\RedirectIfNotParmitted;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Note;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UsersController extends Controller{
    public function __construct(){
//        $this->middleware(RedirectIfNotParmitted::class.':user');
    }
    public function index(){
        return Inertia::render('Users/Index', [
            'title' => 'Users',
            'filters' => Request::all(['search','role_id']),
            'roles' => Role::orderBy('name')
                ->get()
                ->map
                ->only('id', 'name'),
            'users' => User::orderByName()
                ->filter(Request::all(['search','role_id']))
                ->paginate(9)
                ->withQueryString()
                ->through(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'photo' => $user->photo_path ?? null,
                    'deleted_at' => $user->deleted_at,
                ]),
        ]);
    }

    public function create(){
        return Inertia::render('Users/Create',[
            'title' => 'Create a new user',
            'roles' => Role::orderBy('name')
                ->get()
                ->map
                ->only('id', 'name'),
        ]);
    }

    public function store(){
        $userRequest = Request::validate([
            'first_name' => ['required', 'max:50'],
            'last_name' => ['required', 'max:50'],
            'phone' => ['nullable', 'max:25'],
            'email' => ['required', 'max:50', 'email', Rule::unique('users')],
            'password' => ['nullable'],
            'address' => ['nullable'],
            'role_id' => ['nullable'],
        ]);

        if(Request::file('photo')){
            $userRequest['photo_path'] = '/files/'.Request::file('photo')->store('users', ['disk' => 'file_uploads']);
        }

        $customerRole = Role::where('slug', 'customer')->first();
        if(empty($userRequest['role_id']) && !empty($customerRole)){
            $userRequest['role_id'] = $customerRole->id;
        }

        $user = User::create($userRequest);

//        event(new UserCreated(['id' => $user->id, 'password' => $userRequest['password']]));

        return Redirect::route('users')->with('success', 'User created.');
    }

    public function edit(User $user) {
        $a_user = Auth()->user();

        $roles = Role::pluck('id', 'slug')->all();
        if($a_user['role']['slug'] == 'customer'){
            if($user->id !=$a_user['id']){
                return Redirect::back();
            }
        }elseif($a_user['role']['slug'] == 'manager'){
            if($user->id !=$a_user['id'] && $user->role_id != $roles['customer']??0){
                return Redirect::back();
            }
        }
        return Inertia::render('Users/Edit', [
            'title' => $user->name,
            'roles' => Role::orderBy('name')
                ->get()
                ->map
                ->only('id', 'name'),
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'role_id' => $user->role_id,
                'address' => $user->address,
                'photo' => $user->photo_path ?? null,
                'photo_path' => $user->photo_path ?? null,
                'deleted_at' => $user->deleted_at,
            ],
        ]);
    }

    public function all(){
        $users = User::limit(50)->get();
        return response()->json($users);
    }

    public function update(User $user) {
        if (config('app.demo')) {
            return Redirect::back()->with('error', 'Updating user is not allowed for the live demo.');
        }

        Request::validate([
            'first_name' => ['required', 'max:50'],
            'last_name' => ['required', 'max:50'],
            'phone' => ['nullable', 'max:25'],
            'email' => ['required', 'max:50', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable'],
            'address' => ['nullable'],
            'photo' => ['nullable', 'image'],
            'locale' => ['nullable', 'max:5'],
        ]);

        $user->update(Request::only(['first_name', 'last_name', 'phone', 'email', 'address', 'locale']));

        if(!empty(Request::get('role_id'))){
            $user->update(['role_id' => Request::get('role_id')]);
        }

        if(Request::file('photo')){
            if(isset($user->photo_path) && !empty($user->photo_path) && File::exists(public_path($user->photo_path))){
                File::delete(public_path($user->photo_path));
            }
            $user->update(['photo_path' => '/files/'.Request::file('photo')->store('users', ['disk' => 'file_uploads'])]);
        }

        if (Request::get('password')) {
            $user->update(['password' => Request::get('password')]);
        }

        return Redirect::back()->with('success', 'Profile updated.');
    }

    public function destroy(User $user) {

        if (config('app.demo')) {
            return Redirect::back()->with('error', 'Deleting user is not allowed for the live demo.');
        }

        $userId = $user->id;
        $user->delete();
        $this->removeUserFromRelatedTables($userId);

        return Redirect::route('users')->with('success', 'User deleted!');
    }
    public function restore(User $user){
        $user->restore();
        return Redirect::back()->with('success', 'User restored!');
    }

    private function removeUserFromRelatedTables($userId){
        Note::where('user_id', $userId)->update(['user_id' => null]);
        Comment::where('user_id', $userId)->update(['user_id' => null]);
        Attachment::where('user_id', $userId)->update(['user_id' => null]);
        Task::where('user_id', $userId)->update(['user_id' => null]);
        Project::where('user_id', $userId)->update(['user_id' => null]);
    }
}
