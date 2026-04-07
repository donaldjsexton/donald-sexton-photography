<?php

namespace App\Http\Controllers;

use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $normalizedPath = '/'.trim(rawurldecode($request->path()), '/');

        $candidates = collect([
            $normalizedPath,
            rtrim($normalizedPath, '/'),
            rtrim($normalizedPath, '/').'/',
        ])->filter()->unique()->values();

        $redirect = Redirect::query()
            ->whereIn('from_path', $candidates)
            ->first();

        if (! $redirect) {
            throw new NotFoundHttpException();
        }

        return redirect()->to($redirect->to_path, $redirect->status_code);
    }
}
