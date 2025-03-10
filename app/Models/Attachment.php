<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? 'id', $value)->firstOrFail();
    }

    public function task()
    {
        return $this->hasOne(Task::class);
    }

    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comment(){
        return $this->belongsTo(Comment::class, 'comment_id');
    }

    public function scopeOrderByName($query)
    {
        $query->orderBy('name');
    }

    public function getCreatedAtAttribute($date){
        return Carbon::parse($date)->format('Y-m-d H:i:s');
    }
}
