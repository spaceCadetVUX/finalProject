<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaceEncoding;
use Illuminate\Http\Request;

class EncodingController extends Controller
{
    public function index(Request $request)
    {
        $query = FaceEncoding::with('user:id,name,code')
            ->whereHas('user', fn ($q) => $q->whereNull('deleted_at'));

        if ($request->filled('updated_since')) {
            $since = (int) $request->query('updated_since');
            $query->where('created_at', '>=', date('Y-m-d H:i:s', $since));
        }

        $encodings = $query->get()->map(fn ($fe) => [
            'id'       => $fe->id,
            'user_id'  => $fe->user_id,
            'name'     => $fe->user->name,
            'code'     => $fe->user->code,
            'encoding' => $fe->encoding,
        ]);

        return response()->json($encodings);
    }
}
