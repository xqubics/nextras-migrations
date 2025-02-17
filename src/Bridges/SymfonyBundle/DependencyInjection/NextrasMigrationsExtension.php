<?php

/**
 * This file is part of the Nextras community extensions of Nette Framework
 *
 * @license    New BSD License
 * @link       https://github.com/nextras/migrations
 */

namespace Nextras\Migrations\Bridges\SymfonyBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;


class NextrasMigrationsExtension extends Extension
{
	/** @var array */
	protected $dbals = [
		'dibi' => 'Nextras\Migrations\Bridges\Dibi\DibiAdapter',
		'dibi2' => 'Nextras\Migrations\Bridges\Dibi\Dibi2Adapter',
		'dibi3' => 'Nextras\Migrations\Bridges\Dibi\Dibi3Adapter',
		'dibi4' => 'Nextras\Migrations\Bridges\Dibi\Dibi3Adapter',
		'doctrine' => 'Nextras\Migrations\Bridges\DoctrineDbal\DoctrineAdapter',
		'nette' => 'Nextras\Migrations\Bridges\NetteDatabase\NetteAdapter',
		'nextras' => 'Nextras\Migrations\Bridges\NextrasDbal\NextrasAdapter',
	];

	/** @var array */
	protected $drivers = [
		'mysql' => 'Nextras\Migrations\Drivers\MySqlDriver',
		'pgsql' => 'Nextras\Migrations\Drivers\PgSqlDriver',
	];

	/** @var array */
	protected $printers = [
		'console' => 'Nextras\Migrations\Printers\Console',
		'psrLog' => 'Nextras\Migrations\Bridges\PsrLog\PsrLogPrinter',
	];


	public function load(array $configs, ContainerBuilder $container)
	{
		$config = $this->processConfiguration(new Configuration(), $configs);

		$dbalAlias = $config['dbal'];
		$dbalDefinition = new Definition($this->dbals[$dbalAlias]);
		$dbalDefinition->setAutowired(TRUE);

		$driverAlias = $config['driver'];
		$driverDefinition = new Definition($this->drivers[$driverAlias]);
		$driverDefinition->setArgument('$dbal', $dbalDefinition);

		$printerAlias = $config['printer'];
		$printerDefinition = new Definition($this->printers[$printerAlias]);
		$printerDefinition->setAutowired(TRUE);

		$container->addDefinitions([
			'nextras_migrations.dbal' => $dbalDefinition,
			'nextras_migrations.driver' => $driverDefinition,
			'nextras_migrations.printer' => $printerDefinition,
		]);

		$container->addAliases([
			'Nextras\Migrations\IDbal' => 'nextras_migrations.dbal',
			'Nextras\Migrations\IDriver' => 'nextras_migrations.driver',
			'Nextras\Migrations\IPrinter' => 'nextras_migrations.printer',
		]);

		if ($config['diff_generator'] === 'doctrine') {
			$structureDiffGeneratorDefinition = new Definition('Nextras\Migrations\Bridges\DoctrineOrm\StructureDiffGenerator');
			$structureDiffGeneratorDefinition->setAutowired(TRUE);
			$structureDiffGeneratorDefinition->setArgument('$ignoredQueriesFile', $config['ignored_queries_file']);

		} else {
			$structureDiffGeneratorDefinition = NULL;
		}

		foreach ($config['php_params'] as $phpParamKey => $phpParamValue) {
			if (is_string($phpParamValue) && strlen($phpParamValue) > 1 && $phpParamValue[0] === '@') {
				$serviceName = substr($phpParamValue, 1);
				$config['php_params'][$phpParamKey] = $container->getDefinition($serviceName);
			}
		}

		$configurationDefinition = new Definition('Nextras\Migrations\Configurations\DefaultConfiguration');
		$configurationDefinition->setArguments([$config['dir'], $driverDefinition, $config['with_dummy_data'], $config['enforce_order_by_date'], $config['php_params']]);
		$configurationDefinition->addMethodCall('setStructureDiffGenerator', [$structureDiffGeneratorDefinition]);

		$continueCommandDefinition = new Definition('Nextras\Migrations\Bridges\SymfonyConsole\ContinueCommand');
		$continueCommandDefinition->setArguments([$driverDefinition, $configurationDefinition, $printerDefinition]);
		$continueCommandDefinition->addTag('console.command');

		$createCommandDefinition = new Definition('Nextras\Migrations\Bridges\SymfonyConsole\CreateCommand');
		$createCommandDefinition->setArguments([$driverDefinition, $configurationDefinition, $printerDefinition]);
		$createCommandDefinition->addTag('console.command');

		$resetCommandDefinition = new Definition('Nextras\Migrations\Bridges\SymfonyConsole\ResetCommand');
		$resetCommandDefinition->setArguments([$driverDefinition, $configurationDefinition, $printerDefinition]);
		$resetCommandDefinition->addTag('console.command');

		$container->addDefinitions([
			'nextras_migrations.configuration' => $configurationDefinition,
			'nextras_migrations.continue_command' => $continueCommandDefinition,
			'nextras_migrations.create_command' => $createCommandDefinition,
			'nextras_migrations.reset_command' => $resetCommandDefinition,
		]);

		$container->addAliases([
			'Nextras\Migrations\IConfiguration' => 'nextras_migrations.configuration',
		]);

		if ($structureDiffGeneratorDefinition) {
			$container->addDefinitions([
				'nextras_migrations.structure_diff_generator' => $structureDiffGeneratorDefinition,
			]);
		}
	}
}
