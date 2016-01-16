<?php namespace Telenok\Core\Support\Config;

class Theme {

    public static function view($view = null, $data = [], $mergeData = [])
    {
        if ($view)
        {
            $theme = config('telenok.view.theme');
            
            if (strpos($view, '::') !== FALSE && view()->exists($v = str_replace('::', '::' . $theme, $view)))
            {
                return view($v, $data, $mergeData);
            }
            else if (view()->exists($v = $theme . '.' . $view))
            {
                return view($v, $data, $mergeData);
            }
        }

        return view($view, $data, $mergeData);
    }
}