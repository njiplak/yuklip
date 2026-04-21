<?php

namespace App\Utils;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Inertia\Inertia;

class WebResponse
{
    public static function response($result, $redirect = null)
    {
        if ($result instanceof Exception) {
            return back()->withErrors(['errors' => $result->getMessage()]);
        }

        if (is_null($redirect)) {
            return redirect()->back();
        }

        if (is_array($redirect)) {
            [$routeName, $params] = $redirect;
            return Inertia::location(route($routeName, $params));
        }

        return Inertia::location(route($redirect));
    }


    public static function inertia($result, $redirectRoute, $param = null)
    {
        if ($result instanceof Exception) {
            return back()->withErrors('errors', $result->getMessage());
        } else {
            return Inertia::location(route($redirectRoute, $param));
        }
    }

    public static function inertiaRender($result, $render, $param = [])
    {
        if ($result instanceof Exception) {
            return back()->withErrors('errors', $result->getMessage());
        } else {
            return Inertia::render($render, $param ?? $result);
        }
    }

    public static function json($result, $message = 'Success', $status = 200)
    {
        if ($result instanceof Exception) {
            $code = $result instanceof ModelNotFoundException ? 404 : 400;
            return response()->json([
                "message" => $code === 404 ? 'Not found.' : $result->getMessage(),
                "data" => null,
            ], $code);
        } else {
            return response()->json([
                "message" => $message,
                "data" => $result,
            ], $status);
        }
    }
}
