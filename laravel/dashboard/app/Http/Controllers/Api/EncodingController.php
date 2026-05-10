<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEncoding;
use Illuminate\Http\Request;

class EncodingController extends Controller
{
    public function index(Request $request)
    {
        $query = FaceEncoding::with('user:id,name,code,role,department_id', 'user.department:id,name')
            ->whereHas('user', fn ($q) => $q->whereNull('deleted_at'));

        if ($request->filled('updated_since')) {
            $since = (int) $request->query('updated_since');
            $query->where('created_at', '>=', date('Y-m-d H:i:s', $since));
        }

        $encodings = $query->get()->map(fn ($fe) => [
            'id'         => $fe->id,
            'user_id'    => $fe->user_id,
            'name'       => $fe->user->name,
            'code'       => $fe->user->code,
            'role'       => $fe->user->role,
            'department' => $fe->user->department?->name,
            'encoding'   => $fe->encoding,
        ]);

        // Delta sync: trả thêm danh sách user_id bị xóa kể từ lần sync cuối
        if ($request->filled('updated_since')) {
            $sinceDate = date('Y-m-d H:i:s', (int) $request->query('updated_since'));
            $deletedUserIds = FaceEncoding::whereHas('user', fn ($q) =>
                $q->withTrashed()->whereNotNull('deleted_at')->where('deleted_at', '>=', $sinceDate)
            )->pluck('user_id')->unique()->values()->all();

            return response()->json([
                'encodings'        => $encodings,
                'deleted_user_ids' => $deletedUserIds,
            ]);
        }

        return response()->json($encodings);
    }
}
