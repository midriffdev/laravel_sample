<?php

namespace App\Http\Controllers;

use App\Engine\Logic\PrintReportsService;
use App\Engine\Models\MainLocalization;
use App\Http\Requests;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use PDF;
use Illuminate\View\View;

use App\Engine\Models\Localization;
use App\Engine\Models\Recorder;
use App\Engine\Crude\Options\DefaultReportAuthor;
use App\Engine\Crude\Options\MeasurementTimeOptions;
use File;
use App\Engine\Models\Reports;
use App\Engine\Crude\Options\MainLocalizationStatus;
use Carbon\Carbon;
use Illuminate\Http\Response;
use App\Engine\Models\Order;
use Session;
use App\Engine\Logic\AnnualCalculations;
use App\Engine\Logic\RoundDownRadonLevel;
use App\Engine\Models\MassReports;

class ReportsController extends Controller
{
    public function pdfGenerate($localizationId)
    {
        $data = $this->preparePdfGenerate($localizationId);

        $pdf = $data['pdf'];

        return $pdf->stream('form.pdf');
    }

    public function mainLocalizationCalculationsReport($mainLocalizationId)
    {
        $data = $this->prepareMainLocalizationCalculationsReport($mainLocalizationId);

        $pdf = $data['pdf'];

        return $pdf->stream('certificate_main_localization.pdf');
    }

    public function localizationCalculationsReport($localizationId)
    {
        $data = $this->prepareLocalizationCalculationsReport($localizationId);

        $pdf = $data['pdf'];

        $saveFileName = $data['saveFileName'];

        return $pdf->stream("$saveFileName.pdf");
    }

    public function reportPreview($id)
    {
        $fileName = (new Reports)->getFileById($id)->file_name;
        $file = storage_path("upload/$fileName");
        return response()
            //->header('Content-Type', 'application/pdf')
            ->file($file);
    }

    public function mainLocalizationCalculationsReportGeneratorForm1(Request $request)
    {
        $mainLocalizationIds = explode(',', $request->input('ids_form1'));

        $singlePage = $request->input('single_page');

        $isSinglePage = ($singlePage == 'true')? true: false;

        $pdfTemplate = ($isSinglePage)
            ?'pdf.form_preview_single_page'
            :'pdf.form_preview';

        $localization = new Localization;

        $multiPage = [];

        if($mainLocalizationIds) {
            foreach($mainLocalizationIds as $mainLocalizationId) {

                $localizationIds = $localization->getLocalizationIdsByMainLocalizationId($mainLocalizationId)->pluck('id');

                foreach($localizationIds as $localizationId) {

                    $formData = (new Localization)->getFormDataByLocalization($localizationId);
                    $recorders = (new Recorder)->getByLocalizationId($localizationId);
                    $orderData = (new Order)->find($formData->order_id);
                    $differentAddress = PrintReportsService::checkDifferentAddress($formData); //prev $orderData
                    $personResponsible = PrintReportsService::checkPersonResponsible($formData, $orderData);
                    // // dd('test');
                    // dd($formData);
                    // $view = 'pdf.form';

                    if ($formData->measurment_time == MeasurementTimeOptions::LONG_OPTION) {
                        $view = 'pdf.form_long';
                        $formData->measurement_started = explode(' ', $formData->measurement_started)[0];
                        $formData->measurement_ended = explode(' ',$formData->measurement_ended)[0];
                    }

                    $multiPage[] = [
                        'formData' => $formData,
                        'recorders' => $recorders,
                        'orderData' => $orderData,
                        'differentAddress' => $differentAddress,
                        'personResponsible' => $personResponsible
                    ];

                }
            }
        }
        //todo sprawa headera
        // $header = view('pdf.form_header');
        $pdf = PDF::loadView($pdfTemplate,[
                'multipage' => $multiPage,
            ])
            ->setPaper('a4')
            // ->setOrientation('landscape')
            ->setOption('footer-center', trans('reports.page').' [page] '.trans('reports.to').' [toPage]')
            ->setOption('footer-font-size', 8)
            // ->setOption('header-html', $header)
            ;

        return $pdf->stream('Form1multipart.pdf');

    }

    public function mainLocalizationCalculationsReportGeneratorForm2(Request $request)
    {
        
        $mainLocalizationIds = explode(',', $request->input('ids_form2'));
        
        $singlePage = $request->input('single_page');

        $isSinglePage = ($singlePage == 'true')? true: false;

        $pdfTemplate = ($isSinglePage)
            ?'pdf.certificate_main_localization_single_page_preview'
            :'pdf.certificate_main_localization_preview';

        $multiPage= [];

        if($mainLocalizationIds) {
           
            foreach($mainLocalizationIds as $mainLocalizationId) {

                $formData = PrintReportsService::createReportToPrintMainLocalization($mainLocalizationId);
                $localizationIds = (new Localization)->getLocalizationIdsByMainLocalizationId($mainLocalizationId)->pluck('id');
                $orderData = (new Order)->find($formData['mainLocalization']->order_id);
                $formDataLocalization = [];

                foreach($localizationIds as $localizationId){
                    $formDataLocalization[] = PrintReportsService::createReportToPrintLocalization($localizationId);
                }

                $differentAddress = PrintReportsService::checkDifferentAddress($formData['mainLocalization']); //prev $orderData
                $reportAddress = PrintReportsService::checkReportAddress($differentAddress, $orderData, $formData);
                $reportClient = PrintReportsService::checkReportClient($differentAddress);
                $sanitizerData = (new Order)->getSanitizerDataByOrderId($formData['mainLocalization']->order_id);

                $mainLolcalizationAnnualValue = collect($formDataLocalization)->filter(function($item) {
                    return $item['annualValue'] != trans('localizations.no_value');
                })->avg('annualValue');

                $multiPage[] = [
                    'formData' => $formData,
                    'localizationIds' => $localizationIds,
                    'orderData' => $orderData,
                    'formDataLocalization' => $formDataLocalization,
                    'differentAddress' => $differentAddress,
                    'reportAddress' => $reportAddress,
                    'reportClient' => $reportClient,
                    'mainLolcalizationAnnualValue' => $mainLolcalizationAnnualValue,
                    'reportAuthor' => DefaultReportAuthor::ANDREAS_GUHR,
                    'sanitizerData' => $sanitizerData
                ];
            }
        }

        // $header = view('pdf.form_header');
        
        $pdf = PDF::loadView($pdfTemplate,[
            'multipage' => $multiPage,

        ])
        ->setPaper('a4')
        // ->setOrientation('landscape')
        ->setOption('footer-center', trans('reports.page').' [page] '.trans('reports.to').' [toPage]')
        ->setOption('footer-font-size', 8);
        // ->setOption('header-html', $header);

        return $pdf->stream('multiform2.pdf');

    }

    public function mainLocalizationCalculationsReportGeneratorForm2ForMailing($mainLocalizationId)
    {
        // $mainLocalizationIds = $request->input('main_localization_ids');
        // $mainLocalizationIds = $request;
        $isSinglePage = false;

        $pdfTemplate = ($isSinglePage)
            ?'pdf.certificate_main_localization_single_page'
            :'pdf.certificate_main_localization';

        $data = $this->prepareMainLocalizationCalculationsReport($mainLocalizationId);

        $pdf = $data['pdf'];
        $orderData = $data['orderData'];
        $formData = $data['formData'];

        $path = str_slug(
            $orderData->shop_id. '-'.
            $formData['localizations'][0]->compound_id. '-' .
            // $formData['mainLocalization']->shipping_firstname. '-' .
            // $formData['mainLocalization']->shipping_flastname. '-' .
            $formData['mainLocalization']->shipping_street. '-' .
            $formData['mainLocalization']->shipping_city. '-' .
            $formData['mainLocalization']->shipping_company. '-'
        );

        if($formData['localizations'][0]->flat_number) {
            $path = str_slug(
                $orderData->shop_id. '-'.
                $formData['localizations'][0]->compound_id. '-' .
                // $formData['mainLocalization']->shipping_firstname. '-' .
                // $formData['mainLocalization']->shipping_flastname. '-' .
                $formData['mainLocalization']->shipping_street. '-' .
                $formData['mainLocalization']->shipping_city. '-' .
                'lght'. '-' .
                $formData['localizations'][0]->flat_number. '-' .
                $formData['mainLocalization']->shipping_company. '-'
            );
        }

        $filename = storage_path(
            'pdf/form2/' .
            $path .
            // uniqid() .
            '.pdf'
        );
        /* echo $filename;exit; */

        if (!File::exists($filename))
        {
            $pdf->save($filename);
        }

        (new MassReports)->create([
            'main_localization_id' => $mainLocalizationId,
            'file' => $filename
        ]);

        return $filename;
    }

    private function preparePdfGenerate($localizationId, $view = 'pdf.form')
    {
        $formData = (new Localization)->getFormDataByLocalization($localizationId);
        $recorders = (new Recorder)->getByLocalizationId($localizationId);
        $orderData = (new Order)->find($formData->order_id);

        $differentAddress = PrintReportsService::checkDifferentAddress($formData); //prev $orderData
        $personResponsible = PrintReportsService::checkPersonResponsible($formData, $orderData);

        // $header = view('pdf.form_header');
// todo sprawa headera
        // $view = 'pdf.form';

        if ($formData->measurment_time == MeasurementTimeOptions::LONG_OPTION) {
            $view = 'pdf.form_long';
            $formData->measurement_started = explode(' ', $formData->measurement_started)[0];
            $formData->measurement_ended = explode(' ',$formData->measurement_ended)[0];
        }

        $pdf = PDF::loadView($view,[
                'formData' => $formData,
                'recorders' => $recorders,
                'differentAddress' => $differentAddress,
                'orderData' => $orderData,
                'personResponsible' => $personResponsible
            ])
            ->setPaper('a4')
            // ->setOrientation('landscape')
            ->setOption('footer-center', trans('reports.page').' [page] '.trans('reports.to').' [toPage]')
            ->setOption('footer-font-size', 8)
            // ->setOption('header-html', $header)
            ;

        $data = [
            'pdf' => $pdf,
            'orderData' => $orderData,
            'formData' => $formData
        ];

        return $data;
    }

    private function prepareMainLocalizationCalculationsReport($mainLocalizationId, $template = 'pdf.certificate_main_localization')
    {
        $formData = PrintReportsService::createReportToPrintMainLocalization($mainLocalizationId);
        $localizationIds = (new Localization)->getLocalizationIdsByMainLocalizationId($mainLocalizationId)->pluck('id');
        $orderData = (new Order)->find($formData['mainLocalization']->order_id);
        $formDataLocalization = [];

        foreach($localizationIds as $localizationId){
            $formDataLocalization[] = PrintReportsService::createReportToPrintLocalization($localizationId);
        }

        $differentAddress = PrintReportsService::checkDifferentAddress($formData['mainLocalization']); //prev $orderData
        $reportAddress = PrintReportsService::checkReportAddress($differentAddress, $orderData, $formData);
        $reportClient = PrintReportsService::checkReportClient($differentAddress);
        $sanitizerData = (new Order)->getSanitizerDataByOrderId($formData['mainLocalization']->order_id);

        $mainLolcalizationAnnualValue = collect($formDataLocalization)->filter(function($item) {
            return $item['annualValue'] != trans('localizations.no_value');
        })->avg('annualValue');

        $pdf = PDF::loadView($template,[
            'formData' => $formData,
            'formDataLocalization' => $formDataLocalization,
            'orderData' => $orderData,
            'reportAuthor' => DefaultReportAuthor::ANDREAS_GUHR,
            'differentAddress' => $differentAddress,
            'mainLolcalizationAnnualValue' => $mainLolcalizationAnnualValue,
            'reportClient' => $reportClient,
            'reportAddress' => $reportAddress,
            'sanitizerData' => $sanitizerData
        ])
        ->setPaper('a4')
        ->setOption('footer-center', trans('reports.page').' [page] '.trans('reports.to').' [toPage]')
        ->setOption('footer-font-size', 8);

        $data = [
            'pdf' => $pdf,
            'orderData' => $orderData,
            'formData' => $formData
        ];

        return $data;
    }

    private function prepareLocalizationCalculationsReport($localizationId)
    {
        $formData = PrintReportsService::createReportToPrintLocalization($localizationId);
        $orderData = (new Order)->find($formData['mainLocalization']->order_id);
        $differentAddress = PrintReportsService::checkDifferentAddress($formData['mainLocalization']); //prev $orderData
        $reportClient = PrintReportsService::checkReportClient($differentAddress);
        $reportAddress = PrintReportsService::checkReportAddress($differentAddress, $orderData, $formData);
        $personResponsible = PrintReportsService::checkPersonResponsible($formData['localizations'][0],  $orderData);
        $sanitizerData = (new Order)->getSanitizerDataByOrderId($formData['mainLocalization']->order_id);

        $pdf = PDF::loadView('pdf.certificate',[
            'formData' => $formData,
            'reportAuthor' => DefaultReportAuthor::ANDREAS_GUHR,
            'differentAddress' => $differentAddress,
            'orderData' => $orderData,
            'reportClient' => $reportClient,
            'reportAddress' => $reportAddress,
            'personResponsible' => $personResponsible,
            'sanitizerData' => $sanitizerData
        ])
        ->setPaper('a4')
        ->setOption('footer-center', trans('reports.page').' [page] '.trans('reports.to').' [toPage]')
        ->setOption('footer-font-size', 8);

        if ($formData['mainLocalization']->status == MainLocalizationStatus::MAIN_LOC_STATUS_VALIDATED) {
            $date = Carbon::now()->format('Y-m-d-h-i-s');
            $fileName = "$date-location-$localizationId.pdf";
            Reports::create([
                             'file_name' => $fileName,
                             'localization_id' => $localizationId
                             ]);
            $pdf->save(storage_path("upload/$fileName"));
        }
        $saveFileName = $orderData->shop_id." - ".$formData['localizations'][0]->compound_id." ".$reportAddress['firstname']." "
        .$reportAddress['lastname']." - ".$reportAddress['street'];

        $data = [
            'pdf' => $pdf,
            'saveFileName' => $saveFileName
        ];

        return $data;
    }
}
