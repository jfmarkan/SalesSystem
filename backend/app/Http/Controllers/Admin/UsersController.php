<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OnlineStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller {
    public function index(OnlineStatus $online) {
        $rows = DB::table('users as u')
            ->leftJoin('roles as r','r.id','=','u.role_id')
            ->select('u.id','u.first_name','u.last_name','u.username','u.email','u.role_id','u.disabled','r.name as role')
            ->orderBy('u.username')->get()->map(fn($x)=>(array)$x)->all();
        return response()->json($online->mapUsersOnline($rows));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'first_name'=>'required','last_name'=>'required','username'=>'required|unique:users,username',
            'email'=>'required|email|unique:users,email','password'=>'required|min:6','role_id'=>'required|exists:roles,id',
            'disabled'=>'boolean'
        ]);
        $id = DB::table('users')->insertGetId([
            'first_name'=>$data['first_name'],'last_name'=>$data['last_name'],'username'=>$data['username'],
            'email'=>$data['email'],'password'=>Hash::make($data['password']),
            'role_id'=>$data['role_id'],'disabled'=>$data['disabled']??0,'created_at'=>now(),'updated_at'=>now()
        ]);
        return response()->json(DB::table('users')->find($id), 201);
    }

    public function update(Request $request, int $userId) {
        $data = $request->validate([
            'first_name'=>'sometimes','last_name'=>'sometimes',
            'email'=>'sometimes|email|unique:users,email,'.$userId,
            'password'=>'sometimes|min:6','role_id'=>'sometimes|exists:roles,id','disabled'=>'boolean'
        ]);
        if (isset($data['password'])) $data['password'] = Hash::make($data['password']);
        DB::table('users')->where('id',$userId)->update($data + ['updated_at'=>now()]);
        return response()->json(DB::table('users')->find($userId));
    }

    public function destroy(int $userId) {
        DB::table('personal_access_tokens')->where('tokenable_id',$userId)->delete();
        DB::table('users')->where('id',$userId)->delete();
        return response()->json(['ok'=>true]);
    }

    public function kick(int $userId, OnlineStatus $online) {
        $online->kickUser($userId);
        return response()->json(['ok'=>true]);
    }
}
