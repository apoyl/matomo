<?php
/**
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\Live\Reports;

use Piwik\Common;
use Piwik\Plugin\Report;
use Piwik\Plugins\Live\MeasurableSettings;
use Piwik\Plugins\Live\Visualizations\VisitorLog;
use Piwik\Report\ReportWidgetFactory;
use Piwik\Widget\WidgetsList;

class GetLastVisitsDetails extends Base
{
    protected $defaultSortColumn = '';

    protected function init()
    {
        parent::init();
        $this->order = 2;
        $this->categoryId = 'General_Visitors';
        $this->subcategoryId = 'Live_VisitorLog';
    }

    public function getDefaultTypeViewDataTable()
    {
        return VisitorLog::ID;
    }

    public function alwaysUseDefaultViewDataTable()
    {
        return true;
    }

    public function configureWidgets(WidgetsList $widgetsList, ReportWidgetFactory $factory)
    {
        $widget = $factory->createWidget()
                          ->forceViewDataTable(VisitorLog::ID)
                          ->setName('Live_VisitorLog')
                          ->setOrder(10)
                          ->setParameters(array('small' => 1));
        $widgetsList->addWidgetConfig($widget);
    }

    public function isEnabled()
    {
        $idSite = Common::getRequestVar('idSite', 0, 'int');

        if (empty($idSite)) {
            return true;
        }

        $settings = new MeasurableSettings($idSite);

        return $settings->activateVisitorLog->getValue();
    }
}
