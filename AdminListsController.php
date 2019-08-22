<?php

namespace App\Http\Controllers;

use App\Http\Requests;
use Illuminate\Http\Request;
use App\Engine\Models\Order;
use App\Engine\Models\Recorder;
use App\Engine\Models\Localization;
use App\Engine\Models\MainLocalization;
use App\Engine\Crude\Options\UserTypes;
use Session;
use Auth;

class AdminListsController extends AbstractListsController
{
    public function client()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-start', [
            'crudeSetup' => [(new \App\Engine\Crude\Client)->getCrudeSetupData()],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function recorderMove($recorderId)
    {
        $this->onlySuperAdmin();

        $recorder = (new Recorder)->find($recorderId);
        if (!$recorder)
            return redirect('home');

        return view('lists.admin-recorder-move', [
            'recorder' => $recorder,
            'statusList' => null
        ]);
    }

    public function coupons()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\SanitizerCoupon)->getCrudeSetupData(),
                (new \App\Engine\Crude\SanitizerCouponHidden)->getCrudeSetupData()
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function sanitizer()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-start', [
            'crudeSetup' => [(new \App\Engine\Crude\Sanitizer)->getCrudeSetupData()],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function adminOrders()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\OrderNew)->getCrudeSetupData(),
                // (new \App\Engine\Crude\OrderSentToClient)->getCrudeSetupData(),
                // (new \App\Engine\Crude\OrderSentToAltrack)->getCrudeSetupData(),
                (new \App\Engine\Crude\OrderReceived)->getCrudeSetupData(),
                (new \App\Engine\Crude\OrderForValidation)->getCrudeSetupData(),
                (new \App\Engine\Crude\OrderValidated)->getCrudeSetupData(),
                (new \App\Engine\Crude\OrderClosed)->getCrudeSetupData(),
            ],
            'modelName' => 'Order',
            'statusList' => 'OrderStatus'
        ]);
    }

    public function adminRecordersList()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\RecordersList)->getCrudeSetupData(),
                (new \App\Engine\Crude\RecorderReadyForValidation)->getCrudeSetupData(),
                (new \App\Engine\Crude\RecorderRejected)->getCrudeSetupData(),
            ],
            'modelName' => 'Recorder',
            'statusList' => 'RecordersListStatus'
        ]);
    }

    public function adminLocalization()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AdminLocalization)->getCrudeSetupData(),
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function adminMainLocalization()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\AdminMainLocalizationReadyForValidation)->getCrudeSetupData(),
                (new \App\Engine\Crude\AdminMainLocalization)->getCrudeSetupData(),
                (new \App\Engine\Crude\AdminMainLocalizationNotValidated)->getCrudeSetupData(),
                (new \App\Engine\Crude\AdminMainLocalizationRejected)->getCrudeSetupData(),
                (new \App\Engine\Crude\AdminMainLocalizationValidated)->getCrudeSetupData(),
            ],
            'modelName' => 'MainLocalization',
            'statusList' => 'MainLocalizationStatus'
        ]);
    }

    public function dictionaries()
    {
        $this->onlySuperAdmin();

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\Remark)->getCrudeSetupData(),
                (new \App\Engine\Crude\LocationRemark)->getCrudeSetupData(),
                // (new \App\Engine\Crude\SpaceTypes)->getCrudeSetupData()
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function localizationListReport($localizationId)
    {

        \Session::put([
            'crude.reports.localization_id' => $localizationId,
        ]);

        $related = (new MainLocalization)->getMainLocalizationByLocalizationId($localizationId);
        $mainLocalizationId = $related->id;
        $orderId = $related->order_id;

        $related = (new Order)->getUserByOrderId($orderId);
        $userId = $related->user_id;

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\Reports)->getCrudeSetupData(),
            ],
            'breadcrumbs' => [
                [
                    'url' => route('clients'),
                    'label' => 'clients'
                ],
                [
                    'url' => route('orders', $userId),
                    'label' => 'orders'
                ],
                [
                    'url' => route('main_localizations', $orderId),
                    'label' => 'main localizations'
                ],
                [
                    'url' => route('localizations', $mainLocalizationId),
                    'label' => 'localizations'
                ]
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }
}
