<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Redirect;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegacyRedirectController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $normalizedPath = '/'.trim(rawurldecode($request->path()), '/');
        $slug = trim($normalizedPath, '/');

        if ($slug !== '') {
            $page = Page::published()
                ->with(['heroMedia', 'blocks.media'])
                ->where('slug', $slug)
                ->first();

            if ($page) {
                return response()->view('pages.show', ['page' => $page]);
            }
        }

        $candidates = collect([
            $normalizedPath,
            rtrim($normalizedPath, '/'),
            rtrim($normalizedPath, '/').'/',
        ])->filter()->unique()->values();

        $redirect = Redirect::query()
            ->whereIn('from_path', $candidates)
            ->first();

        if (! $redirect) {
            throw new NotFoundHttpException;
        }

        return redirect()->to($redirect->to_path, $redirect->status_code);
    }
}
