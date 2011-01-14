<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id$
 *
 * @category Piwik
 * @package Updates
 */

/**
 * @package Updates
 */
class Piwik_Updates_1_2 extends Piwik_Updates
{
	static function getSql($schema = 'Myisam')
	{
		return array(
			// Various performance improvements schema updates
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
			    DROP `visit_server_date`,
			    DROP INDEX `index_idsite_date_config`,
			    DROP INDEX `index_idsite_datetime_config`,
		    	ADD `visit_entry_idaction_name` INT UNSIGNED NOT NULL AFTER `visit_entry_idaction_url`,
			    ADD `visit_exit_idaction_name` INT UNSIGNED NOT NULL AFTER `visit_exit_idaction_url`,
			    CHANGE `visit_exit_idaction_url` `visit_exit_idaction_url` INT UNSIGNED NOT NULL, 
			    CHANGE `visit_entry_idaction_url` `visit_entry_idaction_url` INT UNSIGNED NOT NULL,
			    ADD `idvisitor` BIGINT( 20 ) UNSIGNED NOT NULL AFTER `idsite`, 
			    ADD `config_id` BIGINT( 20 ) UNSIGNED NOT NULL AFTER `config_md5config`
			   ' => false,
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_link_visit_action') .'` 
				ADD `idsite` INT( 10 ) UNSIGNED NOT NULL AFTER `idlink_va` , 
				ADD `server_time` DATETIME NOT NULL AFTER `idsite`,
				ADD `idvisitor` BIGINT(20) UNSIGNED NOT NULL AFTER `idsite`,
				ADD `idaction_name_ref` INT UNSIGNED NOT NULL AFTER `idaction_name`,
				ADD INDEX `index_idsite_servertime` ( `idsite` , `server_time` )
			   ' => false,

		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'` 
			    ADD `idvisitor` BIGINT( 20 ) UNSIGNED NOT NULL AFTER `idsite`
			   ' => false,
		
			// Migrate 128bits IDs inefficiently stored as 8bytes (256 bits) into 64bits
    		'UPDATE '.Piwik_Common::prefixTable('log_visit') .'
    			SET idvisitor = cast(conv(substring(visitor_idcookie,1,16), 16, 10) as unsigned integer),
    				config_id = cast(conv(substring(config_id,1,16), 16, 10) as unsigned integer)
	   			' => false,	
    		'UPDATE '.Piwik_Common::prefixTable('log_conversion') .'
    			SET idvisitor = cast(conv(substring(visitor_idcookie,1,16), 16, 10) as unsigned integer)
	   			' => false,	
			
			// Drop migrated fields
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
		    	DROP visitor_idcookie, 
		    	DROP config_md5config
		    	' => false,
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_conversion') .'` 
		    	DROP visitor_idcookie
		    	' => false,
		
			// Recreate INDEX on new field
		    'ALTER TABLE `'. Piwik_Common::prefixTable('log_visit') .'` 
		    	ADD INDEX `index_idsite_datetime_config` (idsite, visit_last_action_time, config_id)
		    	' => false,
		
			// Backfill action logs as best as we can
			'UPDATE '.Piwik_Common::prefixTable('log_link_visit_action') .' as action, 
				  	'.Piwik_Common::prefixTable('log_visit') .'  as visit
                SET action.idsite = visit.idsite, 
                	action.server_time = visit.visit_last_action_time, 
                	action.idvisitor = visit.idvisitor
                WHERE action.idvisit=visit.idvisit
                ' => false, 
		
			// New index used max once per request, in case this table grows significantly in the future
			'ALTER TABLE `'. Piwik_Common::prefixTable('option') .'` ADD INDEX ( `autoload` ) ' => false,
		
		);
	}

	static function update()
	{
		Piwik_Updater::updateDatabase(__FILE__, self::getSql());
	}
}

