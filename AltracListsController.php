<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Engine\Models\Order;
use App\Engine\Models\Localization;
use App\Engine\Models\MainLocalization;
use App\Engine\Crude\Options\UserTypes;
use Session;
use Auth;

class AltracListsController extends AbstractListsController
{
    public function altracOrders()
    {
        $this->SuperAdminAndAltrac();

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AltracOrderSent)->getCrudeSetupData(),
                (new \App\Engine\Crude\AltracOrderForValidation)->getCrudeSetupData(),
            ],
            'modelName' => 'Order',
            'statusList' => 'OrderStatus'

        ]);
    }

    public function altracRecorders()
    {
        $this->SuperAdminAndAltrac();

        return view('lists.start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\RecordersList)->getCrudeSetupData(),
                (new \App\Engine\Crude\RecorderReadyForValidation)->getCrudeSetupData(),
                (new \App\Engine\Crude\RecorderRejected)->getCrudeSetupData(),
            ]
        ]);
    }

    public function altracRecordersList()
    {
        $this->SuperAdminAndAltrac();

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\RecorderSentToAltrac)->getCrudeSetupData(),
                (new \App\Engine\Crude\RecorderReadyForValidation)->getCrudeSetupData(),
            ],
            'modelName' => 'Recorder',
            'statusList' => 'RecordersListStatus'
        ]);
    }

    public function altracMainLocalization($orderId = null)
    {
        $this->SuperAdminAndAltrac();

        $altracCanAccessMainLocalizationsIds = (new Order)->getOrderIdsForAltrac()->toArray();

        if($orderId != null && $this->userRole == UserTypes::TYPE_ALTRAC && !in_array($orderId, $altracCanAccessMainLocalizationsIds))
            return abort('403');

        \Session::put([
            'crude.altrac_main_localization.order_id' => $orderId,
        ]);

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AltracMainLocalization)->getCrudeSetupData()
            ],
            'modelName' => 'MainLocalization',
            'statusList' => 'MainLocalizationStatus'
        ]);
    }

    public function altracLocalization($mainLocalizationId)
    {
        $this->onlyAltrac();

        $altracCanAccessMainLocalizationsIds = (new MainLocalization)->getMainLocalizationIdsForAltrac()->toArray();

        if($this->userRole == UserTypes::TYPE_ALTRAC && !in_array($mainLocalizationId, $altracCanAccessMainLocalizationsIds))
            return abort('403');

        \Session::put([
            'crude.altrac_localization.main_localization_id' => $mainLocalizationId,
        ]);

        return view('lists.start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AltracLocalization)->getCrudeSetupData()
            ]
        ]);
    }

    public function altracRecorder($localizationId)
    {
        $this->onlyAltrac();

        $altracCanAccessLocalizationsIds = (new Localization)->getLocalizationIdsForAltrac()->toArray();

        if($this->userRole == UserTypes::TYPE_ALTRAC && !in_array($localizationId, $altracCanAccessLocalizationsIds))
            return abort('403');

        \Session::put([
            'crude.altrac_recorder.localization_id' => $localizationId,
        ]);

        return view('lists.start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AltracRecorder)->getCrudeSetupData()
            ],
        ]);
    }

    public function altracOrderDetailedView($orderId)
    {
        $this->onlyAltrac();

        $altracCanAccessMainLocalizationsIds = (new Order)->getOrderIdsForAltrac()->toArray();

        if($orderId != null && $this->userRole == UserTypes::TYPE_ALTRAC && !in_array($orderId, $altracCanAccessMainLocalizationsIds))
            return abort('403');

        \Session::put([
            'crude.altrac_order_detail.order_id' => $orderId,
        ]);

        return view('lists.detailed', [
            'crudeSetup' => [(new \App\Engine\Crude\AltracOrderDetail)->getCrudeSetupData()],
        ]);
    }

    public function altracMainLocalizationDetailedView($mainLocalizationId)
    {
        $this->onlyAltrac();

        $altracCanAccessMainLocalizationsIds = (new MainLocalization)->getMainLocalizationIdsForAltrac()->toArray();

        if($this->userRole == UserTypes::TYPE_ALTRAC && !in_array($mainLocalizationId, $altracCanAccessMainLocalizationsIds))
            return abort('403');

        \Session::put([
            'crude.altrac_main_localization_detail.main_localization_id' => $mainLocalizationId,
        ]);

        return view('lists.detailed', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AltracMainLocalizationDetail)->getCrudeSetupData(),
            ],
        ]);
    }
}
