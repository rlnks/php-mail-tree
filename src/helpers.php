<?php

if (!function_exists('deepclone')) {
    /**
     * Deep-clone any object (or passthrough for non-objects).
     * Used to duplicate pre-built email node trees before customizing them.
     */
    function deepclone(mixed $obj): mixed
    {
        if (!is_object($obj)) {
            return $obj;
        }
        return unserialize(serialize($obj));
    }
}
