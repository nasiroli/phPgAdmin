<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use NativeBlade\Config\AndroidConfig;
use NativeBlade\Config\DesktopConfig;
use NativeBlade\Config\IosConfig;
use NativeBlade\Config\Permission;
use NativeBlade\Config\PrivacyApi;
use NativeBlade\Facades\NativeBladeConfig;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /**
         * NativeBlade WASM requests spoof $_SERVER with HTTP_HOST=localhost and SERVER_PORT=80, so Laravel
         * would otherwise generate absolute URLs to http://localhost/... (port 80). Livewire then loads
         * those URLs in the real browser and returns 404. Force the configured app URL instead (set
         * APP_URL to the Vite dev origin, e.g. http://127.0.0.1:1420, while running nativeblade:dev).
         */
        $nativeBladePlatform = $_SERVER['NATIVEBLADE_PLATFORM'] ?? null;
        if (null !== $nativeBladePlatform && 'web' !== $nativeBladePlatform) {
            $root = rtrim((string) config('app.url'), '/');
            if ('' !== $root) {
                URL::forceRootUrl($root);
            }
        }

        NativeBladeConfig::desktop(function (DesktopConfig $config) {
            $config->title(config('app.name'))
                ->identifier('com.nasiroli.phppgadmin')
                ->version('1.0.0', 1)
                ->size(1200, 800)
                ->icon('src-tauri/icons/logo.png')
                ->minSize(800, 600)
                ->resizable()
                ->splashBackground('#0a0a0a');
        });

        NativeBladeConfig::android(function (AndroidConfig $config) {
            $config->identifier('com.nasiroli.phppgadmin')
                ->version('1.0.0', 1)
                ->minSdk(28)
                ->targetSdk(34)
                ->orientation('portrait')
                ->statusBar(style: 'dark', color: '#0a0a0a')
                ->navigationBar('#0a0a0a')
                ->splashBackground('#0a0a0a')
                ->permissions([
                    Permission::CAMERA        => 'Take photos for your profile',
                    Permission::LOCATION      => 'Show nearby content',
                    Permission::NOTIFICATIONS => 'Receive updates and reminders',
                ]);
        });

        NativeBladeConfig::ios(function (IosConfig $config) {
            $config->identifier('com.nasiroli.phppgadmin')
                ->version('1.0.0', 1)
                ->minIosVersion('15.0')
                ->orientation('portrait')
                ->statusBar(style: 'dark')
                ->splashBackground('#0a0a0a')
                ->permissions([
                    Permission::CAMERA   => 'Take photos for your profile',
                    Permission::LOCATION => 'Show nearby content',
                    Permission::PHOTOS   => 'Select images from your library',
                ])
                ->privacyManifest([
                    PrivacyApi::USER_DEFAULTS    => PrivacyApi::USER_DEFAULTS_APP,
                    PrivacyApi::FILE_TIMESTAMP   => PrivacyApi::FILE_TIMESTAMP_THIRD_PARTY,
                    PrivacyApi::SYSTEM_BOOT_TIME => PrivacyApi::BOOT_TIME_ELAPSED,
                    PrivacyApi::DISK_SPACE       => PrivacyApi::DISK_SPACE_WRITE_CHECK,
                ]);
        });

        NativeBladeConfig::transition('slide');
    }
}
