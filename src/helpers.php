<?php

if (!function_exists('deepclone')) {
    /**
     * Deep-clone any object (or passthrough for non-objects).
     * Relies on __clone() defined in HasChildren to recursively clone child nodes.
     */
    function deepclone(mixed $obj): mixed
    {
        return is_object($obj) ? clone $obj : $obj;
    }
}
