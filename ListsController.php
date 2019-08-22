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

class ListsController extends AbstractListsController
{
    public function order($userId)
    {
        \Session::put([
            'crude.order.user_id' => $userId
        ]);

        return view('lists.admin-with-status', [
            'crudeSetup' => [(new \App\Engine\Crude\Order)->getCrudeSetupData()],
            'breadcrumbs' => [
                    [
                        'url' => route('clients'),
                        'label' => 'clients'
                    ]
                ],
            'modelName' => 'Order',
            'statusList' => 'OrderStatus'
        ]);
    }

    public function orderDetailedView($orderId)
    {

        $canAccesMainLocalizationsIds = (new Order)->getOrderIdsByUserId($this->accessUserId)->toArray();

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($orderId, $canAccesMainLocalizationsIds))
            return abort('403');

        $userId = (new Order)->getUserIdByOrderId($orderId);
         \Session::put([
            'crude.order_detail.order_id' => $orderId,
        ]);

        return view('lists.detailed', [
            'crudeSetup' => [(new \App\Engine\Crude\OrderDetail)->getCrudeSetupData()],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    //used by all roles
    public function mainLocalization($orderId)
    {

        $canAccesMainLocalizationsIds = (new Order)->getOrderIdsByUserId($this->accessUserId)->toArray();

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($orderId, $canAccesMainLocalizationsIds))
            return abort('403');

        $userId = (new Order)->getUserIdByOrderId($orderId);
         \Session::put([
            'crude.main_localization.order_id' => $orderId,
        ]);

        return view('lists.admin-with-status', [
            'crudeSetup' => [(new \App\Engine\Crude\MainLocalization)->getCrudeSetupData()],
            'breadcrumbs' => [
                [
                    'url' => route('clients'),
                    'label' => 'clients'
                ],
                [
                    'url' => route('orders', $userId),
                    'label' => 'orders'
                ]
            ],
            'modelName' => 'MainLocalization',
            'statusList' => 'MainLocalizationStatus'
        ]);
    }

    public function mainLocalizationDetailedView($mainLocalizationId)
    {
        $canAccesLocalizationIds = (new Localization)->getMainLocalizationIdsByUserId($this->accessUserId)->toArray();

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($mainLocalizationId, $canAccesLocalizationIds))
            return abort('403');

        \Session::put([
            'crude.main_localization_detail.main_localization_id' => $mainLocalizationId,
        ]);

        return view('lists.detailed', [
            'crudeSetup' => [
                (new \App\Engine\Crude\MainLocalizationDetail)->getCrudeSetupData(),
            ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function radonFloorCalculation($mainLocalizationId)
    {
        $related = (new MainLocalization)->getRelatedDataById($mainLocalizationId);

        $userId = $related->user_id;
        $orderId = $related->order_id;

        \Session::put([
            'crude.radon_floor_calculation.main_localization_id' => $mainLocalizationId,
        ]);

        return view('lists.detailed', [
            'crudeSetup' => [
                (new \App\Engine\Crude\RadonFloorCalculation)->getCrudeSetupData(),
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
                    ]
                ],
            'modelName' => null,
            'statusList' => null
        ]);
    }

    //used by all roles
    public function localization($mainLocalizationId)
    {
        $canAccesLocalizationIds = (new MainLocalization)->getMainLocalizationIdsByUserId($this->accessUserId)->toArray();

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($mainLocalizationId, $canAccesLocalizationIds))
            return abort('403');

        $related = (new MainLocalization)->getRelatedDataById($mainLocalizationId);

        $userId = $related->user_id;
        $orderId = $related->order_id;

        $localizationIds = (new \App\Engine\Models\Localization)->getLocalizationIdsByMainLocalizationId($mainLocalizationId)->pluck('id');

        \Session::put([
            'crude.localization.main_localization_id' => $mainLocalizationId,
        ]);

        \Session::put([
            'crude.localization.localization_ids' => $localizationIds,
        ]);

        $superAdminBreadcrumbs = [
            [
                'url' => route('clients'),
                'label' => 'clients'
            ],
            [
                'url' => route('admin_main_localizations'),
                'label' => 'main localizations'
            ]
        ];

        $otherUserBreadcrumbs = [
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
            ]
        ];



        $breadcrumbs = (Auth::user()->getUserRole() == UserTypes::TYPE_SUPER_ADMIN)
            ? $superAdminBreadcrumbs
            : $otherUserBreadcrumbs;


        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\Localization)->getCrudeSetupData(),
                (new \App\Engine\Crude\RecorderWithLocalization)->getCrudeSetupData()
            ],
            'breadcrumbs' => $breadcrumbs,
            'modelName' => 'Recorder',
            'statusList' => 'RecordersListStatus'
        ]);
    }

    //used by all roles
    public function recorder($localizationId)
    {
        $mainLocalizationIds = (new Localization)->getMainLocalizationIdsByUserId($this->accessUserId)->toArray();

        $canAccesLocalizationsIdsForInhabitant = (new Localization)->getLocalizationsIdsByUserId($this->accessUserId)->toArray();

        $canAccesLocalizationsIdsForClient = (new Localization)->getLocalizationsIdsByUserIdForClient($mainLocalizationIds)->toArray();

        $canAccesLocalizationsIds = ($this->userRole == UserTypes::TYPE_INHABITANT)
            ? $canAccesLocalizationsIdsForInhabitant
            : $canAccesLocalizationsIdsForClient;

        if($this->userRole != UserTypes::TYPE_SUPER_ADMIN && !in_array($localizationId, $canAccesLocalizationsIds))
            return abort('403');

        $related = (new Localization)->getRelatedById($localizationId);

        $userId = $related->user_id;
        $orderId = $related->order_id;
        $mainLocalizationId = $related->main_localization_id;

        \Session::put([
            'crude.recorder.localization_id' => $localizationId,
        ]);

        return view('lists.admin-with-status', [
            'crudeSetup' => [
                (new \App\Engine\Crude\Recorder)->getCrudeSetupData()
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
                        'url' => route('localizations',$mainLocalizationId),
                        'label' => 'localizations'
                    ],
                    [
                        'url' => route('recorders', $localizationId),
                        'label' => "recorders: {$localizationId}"
                    ]
                ],
                'modelName' => 'Recorder',
                'statusList' => 'RecordersListStatus'
        ]);
    }

    public function recorderLocalization($recorderId)
    {
        //get localization for recorder
        \Session::put([
            'crude.recorder_localization.recorder_id' => $recorderId
        ]);

        return view('lists.start', [
            'crudeSetup' => [
                (new \App\Engine\Crude\RecorderLocalization)->getCrudeSetupData()
                ]
        ]);
    }
}
