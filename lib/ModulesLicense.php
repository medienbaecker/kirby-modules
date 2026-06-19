<?php

namespace Medienbaecker\Modules;

use Kirby\Cms\App;
use Kirby\Data\Json;
use Kirby\Filesystem\F;
use Kirby\Http\Remote;
use Kirby\Plugin\License;
use Kirby\Plugin\LicenseStatus;
use Kirby\Plugin\Plugin;

class ModulesLicense extends License
{
  private const POLAR_ORG_ID = '4fc1c5b3-25c7-4a31-a11e-badb102b500a';
  private const POLAR_BENEFIT_ID = '93507c70-c173-4a72-972f-8c2a97a057c2';
  private const POLAR_API = 'https://api.polar.sh/v1/customer-portal/license-keys/validate';
  public const BUY_URL = 'https://medienbaecker.com/plugins/modules';
  public const PORTAL_URL = 'https://polar.sh/medienbaecker/portal';
  private const DIALOG = 'modules/activate';
  private const CACHE_MINUTES = 60 * 24;

  public function __construct(Plugin $plugin)
  {
    parent::__construct(
      plugin: $plugin,
      name: 'Modules License',
      link: null,
      status: $this->detectStatus(),
    );
  }

  private function detectStatus(): LicenseStatus
  {
    $key = self::readKey();

    if (empty($key)) {
      return App::instance()->system()->isLocal()
        ? self::makeStatus('demo')
        : self::makeStatus('missing');
    }

    $cache = App::instance()->cache('medienbaecker.modules');
    $cacheKey = 'license.' . md5($key);
    $cached = $cache->get($cacheKey);

    if ($cached !== null) {
      return self::makeStatus($cached === 'granted' ? 'active' : 'invalid');
    }

    $granted = self::validateKey($key);
    $cache->set($cacheKey, $granted ? 'granted' : 'invalid', self::CACHE_MINUTES);

    return self::makeStatus($granted ? 'active' : 'invalid');
  }

  public static function remove(): void
  {
    F::remove(self::licenseFile());
    App::instance()->cache('medienbaecker.modules')->flush();
  }

  public static function activate(string $key): bool
  {
    if (!self::validateKey($key)) {
      return false;
    }

    Json::write(self::licenseFile(), ['key' => $key]);

    $cache = App::instance()->cache('medienbaecker.modules');
    $cache->set('license.' . md5($key), 'granted', self::CACHE_MINUTES);

    return true;
  }

  private static function validateKey(string $key): bool
  {
    try {
      $response = Remote::post(self::POLAR_API, [
        'headers' => ['Content-Type' => 'application/json'],
        'data' => Json::encode([
          'key' => $key,
          'organization_id' => self::POLAR_ORG_ID,
          'benefit_id' => self::POLAR_BENEFIT_ID,
        ])
      ]);

      return $response->code() === 200
        && ($response->json()['status'] ?? null) === 'granted';
    } catch (\Exception) {
      // API unreachable — treat as valid to avoid blocking
      return true;
    }
  }

  // Icons and themes mirror Kirby\Cms\LicenseStatus for the matching cases;
  // only the labels and dialog are plugin-specific.
  private static function makeStatus(string $value): LicenseStatus
  {
    return match ($value) {
      'active' => new LicenseStatus(
        value: 'active',
        icon: 'check',
        label: t('modules.license.licensed'),
        theme: 'positive',
        dialog: self::DIALOG
      ),
      'missing' => new LicenseStatus(
        value: 'missing',
        icon: 'key',
        label: t('modules.license.activate'),
        theme: 'love',
        dialog: self::DIALOG
      ),
      'demo' => new LicenseStatus(
        value: 'demo',
        icon: 'preview',
        label: t('modules.license.demo'),
        theme: 'notice',
        dialog: self::DIALOG
      ),
      default => new LicenseStatus(
        value: 'invalid',
        icon: 'alert',
        label: t('modules.license.invalid'),
        theme: 'negative',
        dialog: self::DIALOG
      ),
    };
  }

  public static function readKey(): string|null
  {
    $file = self::licenseFile();

    if (!F::exists($file)) {
      return null;
    }

    try {
      return Json::read($file)['key'] ?? null;
    } catch (\Exception) {
      return null;
    }
  }

  public static function licenseFile(): string
  {
    return dirname(App::instance()->root('license')) . '/.modules_license';
  }
}
