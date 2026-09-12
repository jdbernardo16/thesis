<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user() && $request->user()->role === 'admin', 403);

        $settings = Setting::orderBy('key')->get()->mapWithKeys(fn ($s) => [
            $s->key => $s->value,
        ])->all();

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }
}
