<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(){
        return AuditLog::paginate(10);
    }
    public function show($id){
        return AuditLog::find($id);
    }
    public function store(Request $request){
        return AuditLog::create($request->all());
    }
    public function update(Request $request, $id){
        $auditLog = AuditLog::findOrFail($id);
        $auditLog->update($request->all());
        return $auditLog;
    }
    public function delete(Request $request, $id){
        $auditLog = AuditLog::findOrFail($id);
        $auditLog->delete();
        return 204;
    }
    public function restore($id){
        $auditLog = AuditLog::withTrashed()->findOrFail($id);
        $auditLog->restore();
        return $auditLog;
    }
    public function forceDelete($id){
        $auditLog = AuditLog::withTrashed()->findOrFail($id);
        $auditLog->forceDelete();
        return 204;
    }
    public function restoreAll(){
        AuditLog::withTrashed()->restore();
        return 204;
    }
    public function forceDeleteAll(){
        AuditLog::onlyTrashed()->forceDelete();
        return 204;
    }
    public function search(Request $request){
        $search = $request->search;
        return AuditLog::where('user_id','=',)->paginate(10);
    }
    public function sort(Request $request){
        $sort = $request->sort;
        return AuditLog::orderBy('created_at', $sort)->paginate(10);
    } 

}
