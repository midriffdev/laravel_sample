<?php

namespace App\Http\Controllers\RecorderForm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Engine\Models\Recorder;
use App\Engine\Models\Localization;
use App\Engine\Models\MainLocalization;
use Validator;
use App\Engine\Crude\Options\SpaceTypeOptions;
use App\Engine\Models\SpaceTypes;
use App\Engine\Crude\Options\FloorOptions;
use App\Engine\Crude\Options\LivingSpaceOptions;
use App\Engine\Crude\Options\BuildingTypeOptions;
use App\Engine\Crude\Options\VentilationSystemOptions;
use App\Engine\Crude\Options\BuildingGroundOptions;
use App\Engine\Crude\Options\HouseholdWaterOptions;
use App\Engine\Crude\Options\BlueConcreteOptions;
use Carbon\Carbon;
use App\Engine\Logic\AutomaticRemarkAdder;

class FormController extends Controller
{
     public function formFilled()
    {
        return view('recorder_auth.form_filled');
    }

    public function personalData()
    {
        $loggedByRecorder = \Session::get('recorder_auth_login');

        if(!$loggedByRecorder) {
            return redirect()->route('login');
        }

        $recorder = new Recorder;

        $mainLocalizationId = \Session::get('recorder_auth_main_localization_id');

        $preparedData = $recorder->getFormDataBymainLocalizationId($mainLocalizationId);

        $connectedRecorders = $recorder->getAllByMainLocalizationId($mainLocalizationId);

        $connectedSanitizerData = (new MainLocalization)->getRelatedSanitizerDataById($mainLocalizationId);


        return view('recorder_auth.form', [
            'personalData' => $preparedData,
            'sanitizerData' => $connectedSanitizerData,
            'recorders' => $connectedRecorders,
            'spaceTypeOptions' => $this->prepareSpaceTypeSelectOptions(),
            'floorOptions' => $this->prepareFloorSelectOptions(),
            'livingSpaceOptions' => $this->prepareLivingSpaceSelectOptions(),
            'buildingTypeOptions' => $this->prepareBuildingTypeOptions(),
            'ventilationSystemOptions' => $this->prepareVentilationSystemOptions(),
            'buildingGroundOptions' => $this->prepareBuildingGroundOptions(),
            'householdWaterOptions' => $this->prepareHouseholdWaterOptions(),
            'blueConcreteOptions' => $this->prepareBlueConcreteOptions()
        ]);
    }

    public function personalDataPost(Request $request)
    {
        // dd($request->all());
        $validator = Validator::make($request->all(), [
            'agree_rodo_save' => 'required',
            'signature_name' => 'required',
            'signature_surname' => 'required',
            'shipping_name' => 'required',
            'shipping_surname' => 'required',
            'shipping_email' => 'required',
            'shipping_phone' => 'required',
            'shipping_street' => 'required',
            'shipping_city' => 'required',
            'shipping_country' => 'required',
            'shipping_zip_code' => 'required',
            'floors_with_livingspaces' => 'required',
            'recorders.*.space_type' => 'max:15',
            'recorders.*.measurement_start' => 'required'
            // 'recorders.*.recorder_id' => 'alpha_num|required'
        ]);

        if ($validator->fails()) {
            return redirect('recorder-form')
                ->withErrors($validator)
                ->withInput();
        }


        $mainLocalizationId = \Session::get('recorder_auth_main_localization_id');

        //main localization data
        $houseName = $request->input('house_name');
        $shippingName = $request->input('shipping_name');
        $shippingSurname = $request->input('shipping_surname');
        $shippingEmail = $request->input('shipping_email');
        $shippingPhone = $request->input('shipping_phone');
        $companyName = $request->input('company_name');
        $shippingStreet = $request->input('shipping_street');
        $shippingCity = $request->input('shipping_city');
        $shippingCountry = $request->input('shipping_country');
        $shippingZipCode = $request->input('shipping_zip_code');
        $buildingType = json_encode($request->input('building_type'));
        $buildingOtherType = $request->input('building_other');
        $buildingYear = $request->input('building_year');
        $rebuildingYear = $request->input('rebuilding_year');
        $ventilationSystem = json_encode($request->input('ventilation_system'));
        $buildingGround = json_encode($request->input('building_ground'));
        $householdWater = json_encode($request->input('household_water'));
        $blueConcrete = $request->input('blue_concrete_used');
        $floorsWithDecoratedRooms = $request->input('floors_with_livingspaces');

        //localizations data
        $flatNumber = $request->input('flat_number');
        $signatureName = $request->input('signature_name');
        $signatureSurname = $request->input('signature_surname');

        //recorders data
        $recorders = $request->input('recorders');

        (new MainLocalization)
            ->where('id', $mainLocalizationId)
            ->update([
                'house_name' => $houseName,
                'shipping_firstname' => $shippingName,
                'shipping_lastname' => $shippingSurname,
                'shipping_company' => $companyName,
                'shipping_street' => $shippingStreet,
                'shipping_city' => $shippingCity,
                'shipping_zip_code' => $shippingZipCode,
                'shipping_country' => $shippingCountry,
                'shipping_phone_number' => $shippingPhone,
                'shipping_email' => $shippingEmail,
                'building_type' => $buildingType,
                'building_other_type' => $buildingOtherType,
                'built_year' => $buildingYear,
                'ventilation_systems' => $ventilationSystem,
                'building_ground' => $buildingGround,
                'household_water' => $householdWater,
                'blue_concrete' => $blueConcrete,
                'floors_with_decorated_rooms' => $floorsWithDecoratedRooms,
                'rebuilding_year' => $rebuildingYear
            ]);

        $measurementStartFromFirstRecorder = collect($recorders)->first()['measurement_start'];
        $measurementEndFromFirstRecorder = collect($recorders)->first()['measurement_end'];

        (new Localization)
            ->where('main_localization_id', $mainLocalizationId)
            ->update([
                'flat_number' => $flatNumber,
                'signature_name' => $signatureName,
                'signature_surname' => $signatureSurname,
                'measurement_started' => $measurementStartFromFirstRecorder,
                'measurement_ended' => $measurementEndFromFirstRecorder
            ]);

        foreach ($recorders as $recorder) {

            $hours = null;

            if(!empty($recorder['measurement_start']) && !empty($recorder['measurement_end'])) {

                $start = Carbon::createFromFormat('Y-m-d', $recorder['measurement_start']);
                $end = Carbon::createFromFormat('Y-m-d', $recorder['measurement_end']);
                $hours = $end->diffInDays($start) * 24;
            }

            (new Recorder)
                ->where('id', $recorder['recorder_id'])
                ->update([
                    // 'space_type_id' => $recorder['space_type'],
                    'space_type' => $recorder['space_type'],
                    'living_space' => $recorder['living_space'],
                    'floor_number' => $recorder['floor_number'],
                    'measurement_started' => $recorder['measurement_start'],
                    'measurement_ended' => $recorder['measurement_end'],
                    'hours' => $hours
                ]);

            $updatedRecorderNumber = (new Recorder)->where('id', $recorder['recorder_id'])->first()->number;

            // (new AutomaticRemarkAdder)->execute($updatedRecorderNumber);

        }

        \Session::put('recorder_auth_login', false);
        return redirect()->route('form_filled');

    }

    private function prepareSpaceTypeSelectOptions()
    {
        $spaceTypeOptions = [];

        foreach((new SpaceTypes)->getSelectData() as $option) {
            $spaceTypeOptions[$option['id']] = $option['label'];
        }

        return $spaceTypeOptions;
    }

    private function prepareFloorSelectOptions()
    {
        $floorOptions = [];

        foreach(FloorOptions::getOptions() as $option) {
            $floorOptions[$option['id']] = $option['label'];
        }

        return $floorOptions;
    }

    private function prepareLivingSpaceSelectOptions()
    {
        $livingSpaceOptions = [];

        foreach(LivingSpaceOptions::getOptions() as $option) {
            $livingSpaceOptions[$option['id']] = $option['label'];
        }

        return $livingSpaceOptions;
    }

    private function prepareBuildingTypeOptions()
    {
        $buildingTypeOptions = [];

        foreach(BuildingTypeOptions::getOptions() as $option) {
            $buildingTypeOptions[$option['id']] = $option['label'];
        }

        return $buildingTypeOptions;
    }

    private function prepareVentilationSystemOptions()
    {
        $ventilationSystemOptions = [];

        foreach(VentilationSystemOptions::getOptions() as $option) {
            $ventilationSystemOptions[$option['id']] = $option['label'];
        }

        return $ventilationSystemOptions;
    }

    private function prepareBuildingGroundOptions()
    {
        $buildingGroundOptions = [];

        foreach(BuildingGroundOptions::getOptions() as $option) {
            $buildingGroundOptions[$option['id']] = $option['label'];
        }

        return $buildingGroundOptions;
    }

    private function prepareHouseholdWaterOptions()
    {
        $householdWaterOptions = [];

        foreach(HouseholdWaterOptions::getOptions() as $option) {
            $householdWaterOptions[$option['id']] = $option['label'];
        }

        return $householdWaterOptions;
    }

    private function prepareBlueConcreteOptions()
    {
        $blueConcreteOptions = [];

        foreach(BlueConcreteOptions::getOptions() as $option) {
            $blueConcreteOptions[$option['id']] = $option['label'];
        }

        return $blueConcreteOptions;
    }

    public function postRecorderMove(Request $request)
    {
        $newLocation = $request->input('location');
        $recorderId = $request->input('recorderId');

        $recorder = (new Recorder)->find($recorderId);
        $location = (new Localization)
            ->select('localizations.id', \DB::raw("CONCAT(orders.shop_id, '-', localizations.compound_id) as localization_number"))
            ->leftJoin('main_localizations', 'main_localizations.id', '=', 'localizations.main_localization_id')
            ->leftJoin('orders', 'orders.id', '=', 'main_localizations.order_id')
            ->where(\DB::raw("CONCAT(orders.shop_id, '-', localizations.compound_id)"), 'LIKE', "%".$newLocation."%")
            ->first();

        if (!$location) {
            return response(json_encode(['error' => true, 'message' => 'Location `'. $newLocation .'` was not found']), 404);
        }

        $recorder->update([
            'localization_id' => $location->id
        ]);

        return response(json_encode(['success' => true, 'message' => trans('recorder.recorder_was_moved', ['newLocation' => $newLocation])]), 200);
    }
}
