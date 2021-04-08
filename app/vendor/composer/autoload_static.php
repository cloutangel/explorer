<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7a681db840bb32d317ef0b89878153e7
{
    public static $files = array (
        'fdebb5c5d6efd2c1a1d3bd653d998fea' => __DIR__ . '/..' . '/muvon/kiss-binary-codec/src/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'Muvon\\KISS\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Muvon\\KISS\\' => 
        array (
            0 => __DIR__ . '/..' . '/muvon/kiss-binary-codec/src',
            1 => __DIR__ . '/..' . '/muvon/kiss-request-trait/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7a681db840bb32d317ef0b89878153e7::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7a681db840bb32d317ef0b89878153e7::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit7a681db840bb32d317ef0b89878153e7::$classMap;

        }, null, ClassLoader::class);
    }
}
