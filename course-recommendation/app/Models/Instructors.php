<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instructors extends Model
{
    use HasFactory, SoftDeletes;
    protected $table = 'instructors';
    protected $primaryKey = 'id';
    protected $fillable = ['user_id', 'bio', 'organization','email_paypal'];
    protected $dates = ['deleted_at'];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

   public function courses(){
    return $this->hasMany(Course::class, 'instructor_id');
   }

}