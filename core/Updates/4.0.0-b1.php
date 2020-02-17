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

        // Move the site search fields of log_visit out of custom variables into their own fields
        $migrations[] = $this->migration->db->addColumn('log_link_visit_action', 'search_cat', 'VARCHAR(200) NULL');
        $migrations[] = $this->migration->db->addColumn('log_link_visit_action', 'search_count', 'INTEGER(10) UNSIGNED NULL');
        $visitActionTable = Common::prefixTable('log_link_visit_action');
        $migrations[] = $this->migration->db->sql("UPDATE $visitActionTable SET search_cat = custom_var_v4 WHERE custom_var_k4 = '_pk_scat'");
        $migrations[] = $this->migration->db->sql("UPDATE $visitActionTable SET search_count = custom_var_v5 WHERE custom_var_k4 = '_pk_scount'");


        return $migrations;
    }

    public function doUpdate(Updater $updater)
    {
        $updater->executeMigrations(__FILE__, $this->getMigrations($updater));
    }
}
