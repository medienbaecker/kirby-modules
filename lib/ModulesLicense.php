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
	private const POLAR_API = 'https://api.polar.sh/v1/customer-portal/license-keys/validate';
	public const BUY_URL = 'https://medienbaecker.com/plugins/modules';
	public const PORTAL_URL = 'https://polar.sh/medienbaecker/portal';
	private const DIALOG = 'modules/activate';
	private const CACHE_MINUTES = 60 * 24;

	public function __construct(Plugin $plugin)
	{
		$status = $this->detectStatus();

		parent::__construct(
			plugin: $plugin,
			name: 'Modules License',
			link: null,
			status: $status,
		);
	}

	private function detectStatus(): LicenseStatus
	{
		$key = $this->readKey();

		if (empty($key)) {
			return App::instance()->system()->isLocal()
				? static::makeStatus('demo')
				: static::makeStatus('missing');
		}

		$cache = App::instance()->cache('medienbaecker.modules');
		$cacheKey = 'license.' . md5($key);
		$cached = $cache->get($cacheKey);

		if ($cached !== null) {
			return static::makeStatus($cached === 'granted' ? 'active' : 'invalid');
		}

		$granted = static::validateKey($key);
		$cache->set($cacheKey, $granted ? 'granted' : 'invalid', static::CACHE_MINUTES);

		return static::makeStatus($granted ? 'active' : 'invalid');
	}

	public static function remove(): void
	{
		F::remove(static::licenseFile());
		App::instance()->cache('medienbaecker.modules')->flush();
	}

	public static function activate(string $key): bool
	{
		if (!static::validateKey($key)) {
			return false;
		}

		Json::write(static::licenseFile(), ['key' => $key]);

		$cache = App::instance()->cache('medienbaecker.modules');
		$cache->set('license.' . md5($key), 'granted', static::CACHE_MINUTES);

		return true;
	}

	private static function validateKey(string $key): bool
	{
		try {
			$response = Remote::post(static::POLAR_API, [
				'headers' => ['Content-Type' => 'application/json'],
				'data' => Json::encode([
					'key' => $key,
					'organization_id' => static::POLAR_ORG_ID,
				])
			]);

			return $response->code() === 200
				&& ($response->json()['status'] ?? null) === 'granted';
		} catch (\Exception) {
			// API unreachable — treat as valid to avoid blocking
			return true;
		}
	}

	private static function makeStatus(string $value): LicenseStatus
	{
		return match ($value) {
			'active' => new LicenseStatus(
				value: 'active',
				icon: 'check',
				label: t('modules.license.licensed'),
				theme: 'positive',
				dialog: static::DIALOG
			),
			'missing' => new LicenseStatus(
				value: 'missing',
				icon: 'key',
				label: t('modules.license.activate'),
				theme: 'love',
				dialog: static::DIALOG
			),
			'demo' => new LicenseStatus(
				value: 'demo',
				icon: 'preview',
				label: t('modules.license.demo'),
				theme: 'notice',
				dialog: static::DIALOG
			),
			default => new LicenseStatus(
				value: 'invalid',
				icon: 'alert',
				label: t('modules.license.invalid'),
				theme: 'negative',
				dialog: static::DIALOG
			),
		};
	}

	public static function readKey(): string|null
	{
		$file = static::licenseFile();

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
