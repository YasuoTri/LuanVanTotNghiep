<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log as FacadesLog;

class AuditLogController extends Controller
{
    public function index(){
        return AuditLog::paginate(10);
    }
    public function show($id){
        return AuditLog::find($id);
    }
    public function store(Request $request){
        $auditLog=AuditLog::create($request->all());
        return response()->json($auditLog);
    }
    public function update(Request $request, $id){
        $auditLog = AuditLog::findOrFail($id);
        $auditLog->update($request->all());
        return response()->json($auditLog);
    }
    public function destroy(Request $request, $id){
        $auditLog = AuditLog::findOrFail($id);
        $auditLog->delete();
        return response()->json(['message' => 'Audit log deleted successfully'], 204);
    }
    public function restore($id){
        $auditLog = AuditLog::withTrashed()->findOrFail($id);
        $auditLog->restore();
        return response()->json($auditLog);
    }
    public function forceDelete($id){
        $auditLog = AuditLog::withTrashed()->findOrFail($id);
        $auditLog->forceDelete();
        return response()->json(['message' => 'Audit log deleted permanently'], 201);
    }
    public function restoreAll(){
        AuditLog::withTrashed()->restore();
        return response()->json(['message' => 'All audit logs restored successfully'], 200);
    }
    public function forceDeleteAll(){
        AuditLog::onlyTrashed()->forceDelete();
        return response()->json(['message' => 'All audit logs deleted permanently'], 204);
    }
    public function search(Request $request){
        $search = $request->all();
        $query = AuditLog::query();

        if (!empty($search['action'])) {
            $query->where('action', 'like', '%' . $search['action'] . '%');
        }

        if (!empty($search['user_id'])) {
            $query->orWhere('user_id', $search['user_id']);
        }
        if (!empty($search['created_at'])) {
            $query->whereDate('created_at', $search['created_at']);
        }
        if (!empty($search['payment_id'])) {
            $query->where('payment_id', '=', $search['payment_id']);
        }

    return response()->json($query->paginate(10));
    }
    public function sort(Request $request){
        $sort = $request->sort;
        return AuditLog::orderBy('created_at', $sort)->paginate(10);
    } 

    public function trashed(){
        return AuditLog::onlyTrashed()->paginate(10);
    }

public function logAction($id,$action, $details = null)
{
    AuditLog::create([
        'payment_id' => $id, // hoặc $this->payment_id nếu không có auth()
        'action' => $action,
        'details' => $details,
        'user_id' => Auth::user()->id, // hoặc $this->user_id nếu không có auth()
    ]);
}

}
