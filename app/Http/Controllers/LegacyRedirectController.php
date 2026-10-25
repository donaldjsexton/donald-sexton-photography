<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Redirect;
use App\Support\LegacyPathRules;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
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
            return $this->redirectForLegacyStructure($normalizedPath);
        }

        return redirect()->to($redirect->to_path, $redirect->status_code);
    }

    /**
     * Last resort before a 404: the structural paths of the retired WordPress
     * site (blog index, archives, XML sitemaps, standalone pages).
     */
    private function redirectForLegacyStructure(string $normalizedPath): Response
    {
        $rule = LegacyPathRules::resolve($normalizedPath);

        if ($rule === null) {
            throw new NotFoundHttpException;
        }

        if ($rule['to'] === null) {
            throw new GoneHttpException;
        }

        return redirect()->to($rule['to'], $rule['status']);
    }
}
