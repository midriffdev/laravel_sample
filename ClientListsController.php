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

class ClientListsController extends AbstractListsController
{
    public function clientOrders()
    {
        $this->onlyClient();

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\ClientOrder)->getCrudeSetupData(),
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function clientMainLocalization($orderId)
    {

        $canAccesMainLocalizationsIds = (new Order)->getOrderIdsByUserId($this->accessUserId)->toArray();

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($orderId, $canAccesMainLocalizationsIds))
            return abort('403');

         \Session::put([
            'crude.client_main_localization.order_id' => $orderId,
        ]);

        return view('lists.admin-start', [
            'crudeSetup' => [(new \App\Engine\Crude\ClientMainLocalization)->getCrudeSetupData()
            ],
            'breadcrumbs' => [
                [
                    'url' => route('client_orders'),
                    'label' => 'orders'
                ]
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function clientLocalization($mainLocalizationId)
    {
        $canAccesLocalizationIds = (new MainLocalization)->getMainLocalizationIdsByUserId($this->accessUserId)->toArray();

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($mainLocalizationId, $canAccesLocalizationIds))
            return abort('403');

        \Session::put([
            'crude.client_localization.main_localization_id' => $mainLocalizationId,
        ]);

        $related = (new MainLocalization)->getRelatedDataById($mainLocalizationId);

        $orderId = $related->order_id;

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\ClientLocalization)->getCrudeSetupData(),
            ],
            'modelName' => null,
            'statusList' => null,

            'breadcrumbs' => [
                    [
                        'url' => route('client_orders'),
                        'label' => 'orders'
                    ],
                    [
                        'url' => route('client_main_localizations', $orderId),
                        'label' => 'main localizations'
                    ]
                ],
        ]);
    }

    public function clientLocalizationList()
    {
        $this->onlyClient();

        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\ClientLocalizationList)->getCrudeSetupData(),
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function clientRecorder($localizationId)
    {
        $mainLocalizationIds = (new Localization)->getMainLocalizationIdsByUserId($this->accessUserId)->toArray();

        $canAccesLocalizationsIdsForInhabitant = (new Localization)->getLocalizationsIdsByUserId($this->accessUserId)->toArray();
        $canAccesLocalizationsIdsForClient = (new Localization)->getLocalizationsIdsByUserIdForClient($mainLocalizationIds)->toArray();

        $canAccesLocalizationsIds = ($this->userRole == UserTypes::TYPE_INHABITANT)
            ? $canAccesLocalizationsIdsForInhabitant
            : $canAccesLocalizationsIdsForClient;

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($localizationId, $canAccesLocalizationsIds))
            return abort('403');

        \Session::put([
            'crude.client_recorder.localization_id' => $localizationId,
        ]);

        $related = (new Localization)->getRelatedById($localizationId);

        $orderId = $related->order_id;
        $mainLocalizationId = $related->main_localization_id;

        if(url()->previous() == route('client_localizations_list')) {
            return view('lists.admin-start', [
                'crudeSetup' => [
                    (new \App\Engine\Crude\ClientRecorder)->getCrudeSetupData()
                ],
                'modelName' => null,
                'statusList' => null,
                'breadcrumbs' => [
                        [
                            'url' => route('client_localizations_list'),
                            'label' => 'client_localizations_list'
                        ],
                    ],
            ]);
        }
        return view('lists.admin-start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\ClientRecorder)->getCrudeSetupData()
            ],
            'modelName' => null,
            'statusList' => null,
            'breadcrumbs' => [
                    [
                        'url' => route('client_orders'),
                        'label' => 'orders'
                    ],
                    [
                        'url' => route('client_main_localizations', $orderId),
                        'label' => 'main localizations'
                    ],
                    [
                        'url' => route('client_localizations',$mainLocalizationId),
                        'label' => 'localizations'
                    ],
                    [
                        'url' => route('client_recorders', $localizationId),
                        'label' => "recorders: {$localizationId}"
                    ]
                ],
        ]);

    }
}
