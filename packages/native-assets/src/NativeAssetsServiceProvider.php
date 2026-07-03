<?php

namespace Vrefos\NativeAssets;

use Illuminate\Support\ServiceProvider;

/**
 * Asset-only NativePHP plugin: contributes no runtime services, it exists so
 * the nativephp.json assets map ships the notification chime into the builds.
 */
class NativeAssetsServiceProvider extends ServiceProvider {}
