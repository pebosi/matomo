<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */

namespace Piwik\Updates;

use Piwik\Common;
use Piwik\Config;
use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Updater;
use Piwik\Updates as PiwikUpdates;
use Piwik\Updater\Migration\Factory as MigrationFactory;

/**
 * Update for version 4.0.0-b1.
 */
class Updates_4_0_0_b1 extends PiwikUpdates
{
    /**
     * @var MigrationFactory
     */
    private $migration;

    public function __construct(MigrationFactory $factory)
    {
        $this->migration = $factory;
    }

    public function getMigrations(Updater $updater)
    {
        $migrations = [];
        $migrations[] = $this->migration->db->changeColumnType('log_action', 'name', 'VARCHAR(4096)');
        $migrations[] = $this->migration->db->changeColumnType('log_conversion', 'url', 'VARCHAR(4096)');

        $customTrackerPluginActive = false;
        if (in_array('CustomPiwikJs', Config::getInstance()->Plugins['Plugins'])) {
            $customTrackerPluginActive = true;
        }

        $migrations[] = $this->migration->plugin->activate('BulkTracking');
        $migrations[] = $this->migration->plugin->deactivate('CustomPiwikJs');
        $migrations[] = $this->migration->plugin->uninstall('CustomPiwikJs');

        if ($customTrackerPluginActive) {
            $migrations[] = $this->migration->plugin->activate('CustomJsTracker');
        }

        if ('utf8mb4' === DbHelper::getDefaultCharset()) {
            $allTables = DbHelper::getTablesInstalled();
            $database = Config::getInstance()->database['dbname'];

            $migrations[] = $this->migration->db->changeColumnType('session', 'id', 'VARCHAR(191)');
            $migrations[] = $this->migration->db->changeColumnType('site_url', 'url', 'VARCHAR(190)');
            $migrations[] = $this->migration->db->changeColumnType('option', 'option_name', 'VARCHAR(191)');

            foreach ($allTables as $table) {
                if (preg_match('/archive_/', $table) == 1) {
                    $tableNameUnprefixed = Common::unprefixTable($table);
                    $migrations[] = $this->migration->db->changeColumnType($tableNameUnprefixed, 'name', 'VARCHAR(190)');
                }
            }

            $migrations[] = $this->migration->db->sql("ALTER DATABASE $database CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;");

            foreach ($allTables as $table) {
                $migrations[] = $this->migration->db->sql("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            }
        }

        return $migrations;
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));

        // switch default charset to utf8mb4 in config if available
        $config = Config::getInstance();
        $config->database['charset'] = DbHelper::getDefaultCharset();
        $config->forceSave();
    }
}
