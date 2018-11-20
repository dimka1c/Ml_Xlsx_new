<?php

namespace vendor\OmegaApp;


class ErrorHandler
{
    public function __construct()
    {
        set_error_handler([$this, 'errorHandler']);
        ob_start();
        register_shutdown_function([$this, 'fatalErrorHandler']);
        set_exception_handler([$this, 'exceptionHandler']);
    }

    public function errorHandler()
    {
        return true;
    }

    public function exceptionHandler(\Exception $e)
    {
        var_dump(['error' => $e->getMessage()], $e->getCode(), '');
    }

    public function fatalErrorHandler()
    {
        $error = error_get_last();
        if ( !empty($error) && $error['type'] & ( E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR ) ) {
            ob_end_clean();
        } else {
            ob_end_flush();
        }
    }

}