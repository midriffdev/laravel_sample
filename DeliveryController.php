<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Engine\Models\Recorder;
use App\Engine\Models\Remark;
use App\Mail\LinkEmailBilling;
use App\Mail\LinkEmailShipping;
use App\Mail\LinkEmailSanitizer;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Engine\Crude\Options\RecordersListStatus;

use App\Mail\SmsMissingDataBilling;
use App\Mail\SmsMissingDataShipping;
use App\Mail\SmsMissingDataSanitizer;

use App\Mail\SmsResultReminderBilling;
use App\Mail\SmsResultReminderShipping;
use App\Mail\SmsResultReminderSanitizer;

use App\Engine\Models\MainLocalization;

class DeliveryController extends Controller
{
    public $forReminderRecorderDetails;

    public function index()
    {
        $lastFrameNumber = max((new Recorder)->getFrameNumber()->toArray());

        \JavaScriptTrans::put(['delivery']);

        return view('delivery.index', [
            'modelName' => null,
            'statusList' => null
        ]);
    }

    public function scanDelivery(Request $request)
    {
        $rawRecorders = $request->input('recorders');

        $frameNumber = $request->input('frame_number');

        $frameDate = $request->input('frame_date');

        $deliveryDate = $frameDate ? $frameDate : Carbon::now();

        $recorders = preg_split('/\r\n|[\r\n]/', $rawRecorders);

        $requiredDataFromClientForm = [
            'main_localization_shipping_firstname',
            'main_localization_shipping_lastname',
            'main_localization_shipping_street',
            'main_localization_shipping_city',
            'main_localization_shipping_zip_code',
            'main_localization_shipping_country',
            'main_localization_shipping_phone_number',
            'main_localization_shipping_email',
            'main_localization_building_type',
            'main_localization_blue_concrete',
            'main_localization_floors_with_decorated_rooms',
            'localization_signature_name',
            'localization_signature_surname',
            'living_space',
            'floor_number',
            'measurement_started',
            'measurement_ended'
        ];

        $recorderModel = new Recorder;

        $recorderDetails = collect([]);

        $correctRecordersList = [];

        $recordersNotExist = [];

        $recordersAlreadyDelivered = [];

        foreach($recorders as $recorderNumber) {
            if($recorderNumber) {
                $recorderData = $recorderModel->getRecorderByNumber($recorderNumber);
                $recorderData = $recorderData;

                $recorderExist = collect($recorderData)->isNotEmpty();
                $notDelivered = empty($recorderData->delivery_date);
                $isDelivered = !empty($recorderData->delivery_date);

                if(!$recorderExist) {
                    $recordersNotExist['not_exist'][$recorderNumber] = $recorderNumber;
                }

                if($recorderExist && $isDelivered) {
                    $recordersAlreadyDelivered[$recorderNumber] = $recorderNumber;
                }

                if($recorderExist && $notDelivered) {
                    $recorderData->frame_number = $frameNumber;

                    $requiredFields = [];
                    foreach($requiredDataFromClientForm as $key => $required) {
                        if(empty($recorderData[$required])
                            ||$recorderData[$required] == 'no_information'
                            // ||$recorderData[$required] == 'missing_information'
                            // ||$recorderData[$required] == '["missing_information"]'
                            ) {
                                $requiredFields[$required] = true;
                        }
                    }

                    $recorderData->requiredFields = $requiredFields;

                    $recorderData->requiredFieldsCount = count($requiredFields);

                    $recorderDetails[$recorderNumber] = $recorderData->toArray();

                    $mainLocalizationRecordersList = $recorderModel->getRecorderNumberBelongsToMainLocalization($recorderData['main_localization_id'])->toArray();

                    $correctRecordersList[] = $mainLocalizationRecordersList;
                }
            }
        }

        $recordersConnectedToMainLocalization = collect($correctRecordersList)->unique()->flatten();

        $missingRecorders = $recordersConnectedToMainLocalization->diff($recorders)->toArray();

        $chunk = $recorderDetails->chunk(12);
        $withChunkFrameRecorderDetails = [];

        foreach($chunk as $key => $recorders) {
            foreach($recorders as $recorderDetails) {
                $mainFrameNumber = $recorderDetails['frame_number'];
                $recorderDetails['frame_number'] = $mainFrameNumber . '-' . $key;
                $withChunkFrameRecorderDetails[] = $recorderDetails;
            }
        }

        $recorderDetails = $withChunkFrameRecorderDetails;

        \Session::put('delivery_email_details',$recorderDetails);


        $requiredFieldsNumber = [];

        foreach($recorderDetails as $recorderRequired) {
            $requiredFieldsNumber[] = count($recorderRequired['requiredFields']);
        }

        $missingFieldsCount = collect($requiredFieldsNumber)->sum();

        $noMissingRecorders = empty($missingRecorders);
        $noRecordersNotExist = empty($recordersNotExist);
        $noRecordersAlreadyDelivered = empty($recordersAlreadyDelivered);
        $noMissingFields = $missingFieldsCount == 0;

        $haveMissingFields = $missingFieldsCount != 0;

        $data = [
            'missingRecorders' => $missingRecorders,
            'recorderDetails' => $recorderDetails,
            'recordersNotExist' =>$recordersNotExist,
            'recordersAlreadyDelivered' => $recordersAlreadyDelivered,
            'haveMissingFields' => $haveMissingFields
        ];

        // if($noMissingRecorders && $noRecordersNotExist && $noRecordersAlreadyDelivered && $noMissingFields) {


            foreach($recorderDetails as $toUpdateRecorder) {

                $recorderModel
                    ->where('number', $toUpdateRecorder['number'])
                    ->update([
                        'delivery_date' => $deliveryDate,
                        'frame_number' => $toUpdateRecorder['frame_number'],
                        'status' => RecordersListStatus::STATUS_SEND_TO_ALTRAC
                    ]);
            }
        // }

        // for every missing recorder, set Remark nr 5 to its first remark column
        if (!empty($missingRecorders)) {
            $remarkNo5 = (new Remark)->getByRemarkNumber(5);
            foreach($missingRecorders as $missingRecorderNumber) {
                $recorderModel
                    ->where('number', $missingRecorderNumber)
                    ->update([
                        'remark_id_1' => $remarkNo5->id
                    ]);
            }
        }

        return $data;
    }


    public function sendBillingReminders()
    {

        $recorderDetails = \Session::get('delivery_email_details');

        $emails = [];

        foreach($recorderDetails as $recorderDataForEmail) {
            if($recorderDataForEmail['requiredFieldsCount'] > 0 ) {
                $emails[] = [
                    'email' => $recorderDataForEmail['main_localization_billing_email'],
                    'mainLocalization_id' => $recorderDataForEmail['main_localization_id']
                ];
            }
        }

        $uniqueEmails = collect($emails)->unique();

        foreach ($uniqueEmails as $data) {

            $recorders = (new Recorder)->getAllByMainLocalizationId($data['mainLocalization_id']);

            $recordersString = mt_rand(100000, 999999) . '_' . mt_rand(100000, 999999) . '_' . $recorders->implode('number', '_') . '_' . mt_rand(100000, 999999) . '_' . mt_rand(100000, 999999);

            if($data['email']) {
                Mail::to($data['email'])->queue(new LinkEmailBilling($recordersString));
            }
        }
    }

    public function sendShippingReminders()
    {

        $recorderDetails = \Session::get('delivery_email_details');


        $emails = [];

        foreach($recorderDetails as $recorderDataForEmail) {
            if($recorderDataForEmail['requiredFieldsCount'] > 0 ) {
                $emails[] = [
                    'email' => $recorderDataForEmail['main_localization_shipping_email'],
                    'mainLocalization_id' => $recorderDataForEmail['main_localization_id']
                ];

            }
        }
        $uniqueEmails = collect($emails)->unique();

        foreach ($uniqueEmails as $data) {

            $recorders = (new Recorder)->getAllByMainLocalizationId($data['mainLocalization_id']);

            $recordersString = mt_rand(100000, 999999) . '_' . mt_rand(100000, 999999) . '_' . $recorders->implode('number', '_') . '_' . mt_rand(100000, 999999) . '_' . mt_rand(100000, 999999);

            if($data['email']) {
                Mail::to($data['email'])->queue(new LinkEmailShipping($recordersString));
            }
        }
    }

    public function sendSanitizerReminders()
    {

        $recorderDetails = \Session::get('delivery_email_details');

        $emails = [];

        foreach($recorderDetails as $recorderDataForEmail) {
            if($recorderDataForEmail['requiredFieldsCount'] > 0 ) {
                $emails[] = [
                    'email' => $recorderDataForEmail['sanitizer_email'],
                    'mainLocalization_id' => $recorderDataForEmail['main_localization_id']
                ];
            }
        }

        $uniqueEmails = collect($emails)->unique();

        foreach ($uniqueEmails as $data) {

            $recorders = (new Recorder)->getAllByMainLocalizationId($data['mainLocalization_id']);

            $recordersString = mt_rand(100000, 999999) . '_' . mt_rand(100000, 999999) . '_' . $recorders->implode('number', '_') . '_' . mt_rand(100000, 999999) . '_' . mt_rand(100000, 999999);

            if($data['email']) {
                Mail::to($data['email'])->queue(new LinkEmailSanitizer($recordersString));
            }
        }
    }

    public function sendSmsMissingDataBilling()
    {
        $recorderDetails = \Session::get('delivery_email_details');

        $phoneNumbers = [];

        foreach($recorderDetails as $recorderDataForSms) {
            if($recorderDataForSms['requiredFieldsCount'] > 0 ) {
                $phoneNumbers[] = [
                    'number' => $recorderDataForSms['main_localization_billing_phone_number']
                ];
            }
        }

        $uniquePhones = collect($phoneNumbers)->unique();

        foreach ($uniquePhones as $phone) {

            $smsAddress = $phone['number'] . '@pixie.se';

            if(!empty($smsAddress)) {
                Mail::to($smsAddress)->queue(new SmsMissingDataBilling());
            }
        }
    }

    public function sendSmsResultReminderBilling()
    {
        $recorderDetails = \Session::get('delivery_email_details');

        $phoneNumbers = [];

        foreach($recorderDetails as $recorderDataForSms) {
            if($recorderDataForSms['requiredFieldsCount'] > 0 ) {
                $phoneNumbers[] = [
                    'number' => $recorderDataForSms['main_localization_billing_phone_number']
                ];
            }
        }

        $uniquePhones = collect($phoneNumbers)->unique();

        foreach ($uniquePhones as $phone) {

            $smsAddress = $phone['number'] . '@pixie.se';

            if(!empty($smsAddress)) {
                Mail::to($smsAddress)->queue(new SmsResultReminderBilling());
            }
        }
    }

    public function sendSmsMissingDataShipping()
    {
        $recorderDetails = \Session::get('delivery_email_details');

        $phoneNumbers = [];

        foreach($recorderDetails as $recorderDataForSms) {
            if($recorderDataForSms['requiredFieldsCount'] > 0 ) {
                $phoneNumbers[] = [
                    'number' => $recorderDataForSms['main_localization_shipping_phone_number']
                ];
            }
        }

        $uniquePhones = collect($phoneNumbers)->unique();

        foreach ($uniquePhones as $phone) {

            $smsAddress = $phone['number'] . '@pixie.se';

            if(!empty($smsAddress)) {
                Mail::to($smsAddress)->queue(new SmsMissingDataShipping());
            }
        }
    }

    public function sendSmsResultReminderShipping()
    {
        $recorderDetails = \Session::get('delivery_email_details');

        $phoneNumbers = [];

        foreach($recorderDetails as $recorderDataForSms) {
            if($recorderDataForSms['requiredFieldsCount'] > 0 ) {
                $phoneNumbers[] = [
                    'number' => $recorderDataForSms['main_localization_shipping_phone_number']
                ];
            }
        }

        $uniquePhones = collect($phoneNumbers)->unique();

        foreach ($uniquePhones as $phone) {

            $smsAddress = $phone['number'] . '@pixie.se';

            if(!empty($smsAddress)) {
                Mail::to($smsAddress)->queue(new SmsResultReminderShipping());
            }
        }
    }

    public function sendSmsMissingDataSanitizer()
    {
        $recorderDetails = \Session::get('delivery_email_details');

        $phoneNumbers = [];

        foreach($recorderDetails as $recorderDataForSms) {
            if($recorderDataForSms['requiredFieldsCount'] > 0 ) {
                $phoneNumbers[] = [
                    'number' => $recorderDataForSms['sanitizer_phone_number']
                ];
            }
        }

        $uniquePhones = collect($phoneNumbers)->unique();

        foreach ($uniquePhones as $phone) {

            $smsAddress = $phone['number'] . '@pixie.se';

            if(!empty($smsAddress)) {
                Mail::to($smsAddress)->queue(new SmsMissingDataSanitizer());
            }
        }
    }

    public function sendSmsResultReminderSanitizer()
    {
        $recorderDetails = \Session::get('delivery_email_details');

        $phoneNumbers = [];

        foreach($recorderDetails as $recorderDataForSms) {
            if($recorderDataForSms['requiredFieldsCount'] > 0 ) {
                $phoneNumbers[] = [
                    'number' => $recorderDataForSms['sanitizer_phone_number']
                ];
            }
        }

        $uniquePhones = collect($phoneNumbers)->unique();

        foreach ($uniquePhones as $phone) {

            $smsAddress = $phone['number'] . '@pixie.se';

            if(!empty($smsAddress)) {
                Mail::to($smsAddress)->queue(new SmsResultReminderSanitizer());
            }
        }
    }


}
