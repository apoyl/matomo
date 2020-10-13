<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Live;

use Piwik\Cache;
use Piwik\CacheId;
use Piwik\API\Request;
use Piwik\Common;
use Piwik\Container\StaticContainer;

/**
 *
 */
class Live extends \Piwik\Plugin
{
    protected static $visitorProfileEnabled = null;
    protected static $visitorLogEnabled = null;
    protected static $siteIdLoaded = null;

    /**
     * @see \Piwik\Plugin::registerEvents
     */
    public function registerEvents()
    {
        return array(
            'AssetManager.getJavaScriptFiles'        => 'getJsFiles',
            'AssetManager.getStylesheetFiles'        => 'getStylesheetFiles',
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
            'Live.renderAction'                      => 'renderAction',
            'Live.renderActionTooltip'               => 'renderActionTooltip',
            'Live.renderVisitorDetails'              => 'renderVisitorDetails',
            'Live.renderVisitorIcons'                => 'renderVisitorIcons',
            'Template.jsGlobalVariables'             => 'addJsGlobalVariables',
            'API.getPagesComparisonsDisabledFor'     => 'getPagesComparisonsDisabledFor',
        );
    }

    public function getPagesComparisonsDisabledFor(&$pages)
    {
        $pages[] = 'General_Visitors.Live_VisitorLog';
        $pages[] = 'General_Visitors.General_RealTime';
    }

    public function addJsGlobalVariables(&$out)
    {
        try {
            self::loadSettings();
        } catch (\Exception $e) {
            // ignore exceptions. an exception might be thrown if the session timed out
        }

        $actionsToDisplayCollapsed = (int)StaticContainer::get('Live.pageViewActionsToDisplayCollapsed');
        $out .= "
        piwik.visitorLogEnabled = ".json_encode(self::$visitorLogEnabled).";
        piwik.visitorProfileEnabled = ".json_encode(self::$visitorProfileEnabled).";
        piwik.visitorLogActionsToDisplayCollapsed = $actionsToDisplayCollapsed;
        ";
    }

    public static function isVisitorLogEnabled($idSite = null)
    {
        self::loadSettings($idSite);

        return self::$visitorLogEnabled;
    }

    public static function isVisitorProfileEnabled($idSite = null)
    {
        self::loadSettings($idSite);

        return self::$visitorProfileEnabled;
    }

    private static function loadSettings($idSite = null)
    {
        if (empty($idSite)) {
            $idSite = Common::getRequestVar('idSite', 0, 'int');
        }

        if (!is_null(self::$visitorProfileEnabled) && !is_null(self::$visitorLogEnabled) && $idSite == self::$siteIdLoaded) {
            return; // settings already loaded
        }

        self::$siteIdLoaded = $idSite;
        self::$visitorProfileEnabled = true;
        self::$visitorLogEnabled = true;

        if (!empty($idSite)) {
            $settings = new MeasurableSettings($idSite);

            self::$visitorProfileEnabled = $settings->activateVisitorProfile->getValue();
            self::$visitorLogEnabled = $settings->activateVisitorLog->getValue();
        }
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/Live/stylesheets/live.less";
        $stylesheets[] = "plugins/Live/stylesheets/visitor_profile.less";
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = "node_modules/visibilityjs/lib/visibility.core.js";
        $jsFiles[] = "plugins/Live/javascripts/live.js";
        $jsFiles[] = "plugins/Live/javascripts/SegmentedVisitorLog.js";
        $jsFiles[] = "plugins/Live/javascripts/visitorActions.js";
        $jsFiles[] = "plugins/Live/javascripts/visitorProfile.js";
        $jsFiles[] = "plugins/Live/javascripts/visitorLog.js";
        $jsFiles[] = "plugins/Live/javascripts/rowaction.js";
        $jsFiles[] = "plugins/Live/angularjs/live-widget-refresh/live-widget-refresh.directive.js";
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $translationKeys[] = "Live_VisitorProfile";
        $translationKeys[] = "Live_ClickToViewAllActions";
        $translationKeys[] = "Live_NoMoreVisits";
        $translationKeys[] = "Live_ShowMap";
        $translationKeys[] = "Live_HideMap";
        $translationKeys[] = "Live_PageRefreshed";
        $translationKeys[] = "Live_RowActionTooltipTitle";
        $translationKeys[] = "Live_RowActionTooltipDefault";
        $translationKeys[] = "Live_RowActionTooltipWithDimension";
        $translationKeys[] = "Live_SegmentedVisitorLogTitle";
        $translationKeys[] = "General_Segment";
        $translationKeys[] = "General_And";
        $translationKeys[] = 'Live_ClickToSeeAllContents';
    }

    public function renderAction(&$renderedAction, $action, $previousAction, $visitorDetails)
    {
        $visitorDetailsInstances = Visitor::getAllVisitorDetailsInstances();
        foreach ($visitorDetailsInstances as $instance) {
            $renderedAction .= $instance->renderAction($action, $previousAction, $visitorDetails);
        }
    }

    public function renderActionTooltip(&$tooltip, $action, $visitInfo)
    {
        $detailEntries = [];
        $visitorDetailsInstances = Visitor::getAllVisitorDetailsInstances();

        foreach ($visitorDetailsInstances as $instance) {
            $detailEntries = array_merge($detailEntries, $instance->renderActionTooltip($action, $visitInfo));
        }

        usort($detailEntries, function($a, $b) {
            return version_compare($a[0], $b[0]);
        });

        foreach ($detailEntries AS $detailEntry) {
            $tooltip .= $detailEntry[1];
        }
    }

    public function renderVisitorDetails(&$renderedDetails, $visitorDetails)
    {
        $detailEntries = [];
        $visitorDetailsInstances = Visitor::getAllVisitorDetailsInstances();

        foreach ($visitorDetailsInstances as $instance) {
            $detailEntries = array_merge($detailEntries, $instance->renderVisitorDetails($visitorDetails));
        }

        usort($detailEntries, function($a, $b) {
            return version_compare($a[0], $b[0]);
        });

        foreach ($detailEntries AS $detailEntry) {
            $renderedDetails .= $detailEntry[1];
        }
    }

    public function renderVisitorIcons(&$renderedDetails, $visitorDetails)
    {
        $visitorDetailsInstances = Visitor::getAllVisitorDetailsInstances();
        foreach ($visitorDetailsInstances as $instance) {
            $renderedDetails .= $instance->renderIcons($visitorDetails);
        }
    }

    /**
     * Returns the segment for the most recent visitor id
     *
     * This method uses the transient cache to ensure it returns always the same id within one request
     * as `Request::processRequest('Live.getMostRecentVisitorId')` might return different ids on each call
     *
     * @return mixed|string
     */
    public static function getSegmentWithVisitorId()
    {
        $cache   = Cache::getTransientCache();
        $cacheId = 'segmentWithVisitorId';

        if ($cache->contains($cacheId)) {
            return $cache->fetch($cacheId);
        }

        $segment = Request::getRawSegmentFromRequest();
        if (!empty($segment)) {
            $segment = urldecode($segment) . ';';
        }

        $idVisitor = Common::getRequestVar('visitorId', false);
        if ($idVisitor === false) {
            $idVisitor = Request::processRequest('Live.getMostRecentVisitorId');
        }

        $result = urlencode($segment . 'visitorId==' . $idVisitor);
        $cache->save($cacheId, $result);

        return $result;
    }
}