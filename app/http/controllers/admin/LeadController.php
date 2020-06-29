<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\Admin\LeadsDataTable;
use App\GdprSetting;
use App\Helper\Reply;
use App\Http\Requests\CommonRequest;
use App\Http\Requests\Gdpr\SaveConsentLeadDataRequest;
use App\Http\Requests\Lead\StoreRequest;
use App\Http\Requests\Lead\UpdateRequest;
use App\Lead;
use App\LeadAgent;
use App\LeadFollowUp;
use App\LeadContact;
use App\LeadSource;
use App\LeadStatus;
use App\PurposeConsent;
use App\PurposeConsentLead;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;
use Twilio\Rest\Client;
use Twilio\Twiml;

use Illuminate\Support\Facades\Notification;
use App\SmtpSetting;
use App\Notifications\SendLeadEmail;
use App\LeadEmailHistory; 
use Auth;

class LeadController extends AdminBaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->pageIcon = __('icon mdi mdi-accounts-list-alt');
        $this->pageTitle = __('app.menu.lead');
        $this->middleware(function ($request, $next) {
            if (!in_array('leads', $this->modules)) {
                abort(403);
            }
            return $next($request);
        });
    }

    public function index(LeadsDataTable $dataTable, $blade='')
    {
        $this->totalLeads = Lead::all();

        $this->totalClientConverted = $this->totalLeads->filter(function ($value, $key) {
            return $value->client_id != null;
        });
        $this->totalLeads = Lead::all()->count();
        $this->totalClientConverted = $this->totalClientConverted->count();

        $this->pendingLeadFollowUps = LeadFollowUp::where(\DB::raw('DATE(next_follow_up_date)'), '<=', Carbon::today()->format('Y-m-d'))
            ->join('leads', 'leads.id', 'lead_follow_up.lead_id')
            ->where('leads.next_follow_up', 'yes')
            ->get();
        $this->pendingLeadFollowUps = $this->pendingLeadFollowUps->count();
        if ( $blade == 'dashboard'){

            return $dataTable->render('admin.lead.dashboard', $this->data);
        }else{

            return $dataTable->render('admin.lead.index', $this->data);
        }
    }

    public function show($id)
    {
        $this->lead = Lead::findOrFail($id);
        return view('admin.lead.show', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->leadAgents = LeadAgent::with('user')->get();
        $this->sources = LeadSource::all();
        $this->status = LeadStatus::all();
        return view('admin.lead.create', $this->data);
    }
	
	/**
     * display sms portal.
     *
     * 
     */
	public function SMSPortal($leadID)
    {
        $this->lead = Lead::findOrFail($leadID);
        return view('admin.lead.sms-portal', $this->data);
    }

    public function communicationModal($leadID)
    {
        $this->lead = Lead::findOrFail($leadID);
        return view('admin.lead.sms-portal', $this->data);
    }
	
	/* return sms history in ajax */
	public function ajaxSMSLog($leadID){
		
		$this->lead = Lead::findOrFail($leadID);
		
		//getting messages for lead
		$account_sid = getenv("TWILIO_SID");
		$auth_token = getenv("TWILIO_AUTH_TOKEN");
		$twilio_number = getenv("TWILIO_NUMBER");
		$client = new Client($account_sid, $auth_token);
		
		//getting all inbound sms	
		$inbound_messages = $client->messages
			->read(
				array( "from" => $twilio_number ,"to" => $this->lead->mobile)
				,6000
			);
		//formating inbound sms	
		$inbound_messages = $this->renderSMSRecords($inbound_messages);	
			
		//getting all outbound sms	
		$outbound_messages = $client->messages
			->read(
				array( "from" => $this->lead->mobile ,"to" => $twilio_number)
				,6000
			);
		//formating outbound sms		
		$outbound_messages = $this->renderSMSRecords($outbound_messages);
			
			
		//get all inbound calls	
		$inbound_calls = $client->calls
			->read(
				array( "from" => $twilio_number ,"to" => $this->lead->mobile)
				,6000
			);
		//formating inbound calls	
		$inbound_calls = $this->renderCallRecords($inbound_calls);	
		
		//getting all outbound calls	
		$outbound_calls = $client->calls
			->read(
				array( "from" => $this->lead->mobile ,"to" => $twilio_number)
				,6000
			);	
		//formating outbound calls
		$outbound_calls = $this->renderCallRecords($outbound_calls);	

		//get emails
		$leademailhistory = LeadEmailHistory::where('lead_id', '=', $this->lead->id)->get();
		//formating emails calls
		$leademailhistory = $this->renderEmails($leademailhistory);
			
		$a = array_merge($outbound_messages, $inbound_messages,$inbound_calls,$outbound_calls,$leademailhistory);
		$ord = array();
		foreach ($a as $key => $value){
			$ord[] = strtotime($value['dateSorting']);
		}
		array_multisort($ord, SORT_ASC, $a);
		$this->data['messages']	= $a;
		$view = view("admin.lead.ajax-sms-log",$this->data)->render();
		return response()->json(['html'=>$view]);
	}
	
	private function renderCallRecords($calls){
		$data = array();
		if(!empty($calls)){
			foreach($calls as $call){
				
				$row['type'] = 'call';
				$row['dateSorting'] = $call->dateCreated->format('Y-m-d H:i:s');
				$row['date'] = $call->dateCreated->format('D, d M Y G:ia');
				$row['direction'] = $call->direction;
				$row['from'] = $call->fromFormatted;
				$row['to'] = $call->toFormatted;
				
				if (strpos($call->direction, 'outbound') !== false) {
					$row['body'] = 'Outgoing Call:';
				}
				elseif (strpos($call->direction, 'inbound') !== false) {
					$row['body'] = 'Incoming Call:';
				}
				$row['duration'] = $call->duration;
				$row['status'] = $call->status;
				$data[] = $row;
			}
		}
		return $data;
	}
	
	private function renderSMSRecords($messages){
		$data = array();
		if(!empty($messages)){
			foreach($messages as $sms){
				$row['type'] = 'sms';
				$row['dateSorting'] = $sms->dateSent->format('Y-m-d H:i:s');
				$row['date'] = $sms->dateSent->format('D, d M Y G:ia');
				$row['direction'] = $sms->direction;
				$row['from'] = $sms->from;
				$row['to'] = $sms->to;
				$row['body'] = $sms->body;
				$row['status'] = $sms->status;
				
				$data[] = $row;
			}
		}
		return $data;
	}
	
	
	private function renderEmails($emails){
		$data = array();
		if(!empty($emails)){
			foreach($emails as $e_mail){
				//echo '<pre>';print_r($e_mail);exit();
				$row['type'] = 'email';
				$row['dateSorting'] = $e_mail->Datesent;
				$row['date'] = date('D, d M Y G:ia',strtotime($e_mail->Datesent));
				$row['direction'] = $e_mail->direction;
				$row['from'] = $e_mail->from;
				$row['to'] = $e_mail->to;
				$row['body'] = $e_mail->body;
				$row['status'] = $e_mail->status;
				$data[] = $row;
			}
		}
		return $data;
	}
	
	/**
     * send test sms to client.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendSMS($leadID,Request $request)
    {
		$this->lead = Lead::findOrFail($leadID);
        $account_sid = getenv("TWILIO_SID");
		$auth_token = getenv("TWILIO_AUTH_TOKEN");
		$twilio_number = getenv("TWILIO_NUMBER");
		$client = new Client($account_sid, $auth_token);
		$client->messages->create($this->lead->mobile,['from' => $twilio_number, 'body' => $request->sms_text] );
		return response()->json(['status'=>'success','message'=>'Message Sent Successfully']);	
    }
	
	/**
     * send call to lead.
     *
     * 
     */
	public function sendCall($leadID)
    {
        $this->lead = Lead::findOrFail($leadID);

		//getting messages for lead
		$account_sid = getenv("TWILIO_SID");
		$auth_token = getenv("TWILIO_AUTH_TOKEN");
		$twilio_number = getenv("TWILIO_NUMBER");
		$client = new Client($account_sid, $auth_token);
		$call = $client->calls
               ->create($this->lead->mobile, // to
                        $twilio_number, // from
                        array("url" => "http://demo.twilio.com/docs/voice.xml")
               );
			   
		return response()->json(['status'=>$call->status,'display_status'=>ucwords($call->status),'sid'=>$call->sid]);	   
		
    }
	
	
	/**
     * get call status.
     *
     * 
     */
	public function getCallStatus(Request $request)
    {
        $sid = $request->sid;
		//getting messages for lead
		$account_sid = getenv("TWILIO_SID");
		$auth_token = getenv("TWILIO_AUTH_TOKEN");
		$twilio_number = getenv("TWILIO_NUMBER");
		$client = new Client($account_sid, $auth_token);
		$call = $client->calls($sid)->fetch();   
		return response()->json(['status'=>$call->status,'display_status'=>ucwords($call->status),'sid'=>$call->sid,'duration'=>$call->duration]);	   
		
    }
	
	public function HangupCall(Request $request){
		$sid = $request->sid;
		//getting messages for lead
		$account_sid = getenv("TWILIO_SID");
		$auth_token = getenv("TWILIO_AUTH_TOKEN");
		$twilio_number = getenv("TWILIO_NUMBER");
		$client = new Client($account_sid, $auth_token);
		$call = $client->calls($sid)->fetch();
		if($call->status == 'in-progress'){
			$call = $client->calls($sid)->update(array("status" => "completed")); 
		}
		else{
			$call = $client->calls($sid)->update(array("status" => "canceled")); 
		}
		$call = $client->calls($sid)->fetch();  
		return response()->json(['status'=>$call->status,'display_status'=>ucwords($call->status),'sid'=>$call->sid,'duration'=>$call->duration]);
	}

    
	public function sendEmail($leadID,Request $request)
	{  
		$this->lead = Lead::findOrFail($leadID);
		$smtp = SmtpSetting::first();
        $response = $smtp->verifySmtp();

        if ($response['success']) {
            Notification::route('mail', $this->lead->client_email)->notify(new SendLeadEmail('Notification',$request->sms_text));
			$this->saveEmailHistory($this->lead,$request,$smtp,'delivered');
            return Reply::success('E-mail was sent successfully');
        }
		$this->saveEmailHistory($this->lead,$request,$smtp,'undelivered');
        return Reply::error($response['message']);
	}
	
	public function saveEmailHistory($lead,$request,$smtp,$status = 'delivered'){
		
		$leademailhistory 				= new LeadEmailHistory();
		$leademailhistory->lead_id 		= $this->lead->id;
        $leademailhistory->direction	= 'outbound';
        $leademailhistory->from 		= $smtp->mail_username;
        $leademailhistory->to 			= $lead->client_email;
        $leademailhistory->body 		= $request->sms_text;
        $leademailhistory->status 		= $status;
        $leademailhistory->Datesent 	= gmdate("Y-m-d H:i:s");
		$leademailhistory->save();
	}
	
	/**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreRequest $request)
    {
        $lead = new Lead();
        $lead->company_name = $request->company_name;
        $lead->website = $request->website;
        $lead->address = $request->address;
        $lead->client_name = $request->client_name;
        $lead->client_email = $request->client_email;
        $lead->mobile = $request->mobile;
        $lead->note = $request->note;
        $lead->next_follow_up = $request->next_follow_up;
        $lead->agent_id = $request->agent_id;
        $lead->source_id = $request->source_id;
        $lead->save();
        $lead_follow_up = new LeadFollowUp();
        $lead_follow_up->lead_id = $lead->id;
        $lead_follow_up->next_follow_up_date =  date("Y-m-d H:i:s", strtotime('+24 hours'));
        $lead_follow_up->save();
        //log search
        $this->logSearchEntry($lead->id, $lead->client_name, 'admin.leads.show', 'lead');
        $this->logSearchEntry($lead->id, $lead->client_email, 'admin.leads.show', 'lead');
        if (!is_null($lead->company_name)) {
            $this->logSearchEntry($lead->id, $lead->company_name, 'admin.leads.show', 'lead');
        }

        return Reply::redirect(route('admin.leads.index'), __('messages.LeadAddedUpdated'));
    }


    public function storeLead(StoreRequest $request)
    {
        $lead = new Lead();
        $lead->company_name = $request->company_name;
        $lead->website = $request->website;
        $lead->address = $request->address;
        $lead->client_name = $request->client_name;
        $lead->client_email = $request->client_email;
        $lead->mobile = $request->mobile;
        $lead->note = $request->note;
        $lead->next_follow_up = $request->next_follow_up;
        $lead->agent_id = $request->agent_id;
        $lead->source_id = $request->source_id;
        $lead->save();

        //log search
        $this->logSearchEntry($lead->id, $lead->client_name, 'admin.leads.show', 'lead');
        $this->logSearchEntry($lead->id, $lead->client_email, 'admin.leads.show', 'lead');
        if (!is_null($lead->company_name)) {
            $this->logSearchEntry($lead->id, $lead->company_name, 'admin.leads.show', 'lead');
        }

        return Reply::redirect(route(''), __('messages.LeadAddedUpdated'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->leadAgents = LeadAgent::with('user')->get();
        $this->lead = Lead::findOrFail($id);
        $this->sources = LeadSource::all();
        $this->status = LeadStatus::all();
        return view('admin.lead.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $lead->company_name = $request->company_name;
        $lead->website = $request->website;
        $lead->address = $request->address;
        $lead->client_name = $request->client_name;
        $lead->client_email = $request->client_email;
        $lead->mobile = $request->mobile;
        $lead->agent_id = $request->agent_id;
        $lead->note = $request->note;
        $lead->status_id = $request->status;
        $lead->source_id = $request->source_id;
        $lead->next_follow_up = $request->next_follow_up;

        $lead->save();

        return Reply::redirect(route('admin.leads.index'), __('messages.LeadUpdated'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        Lead::destroy($id);
        return Reply::success(__('messages.LeadDeleted'));
    }

    /**
     * @param CommonRequest $request
     * @return array
     */
    public function changeStatus(CommonRequest $request)
    {
        $lead = Lead::findOrFail($request->leadID);
        $lead->status_id = $request->statusID;
        $lead->save();

        return Reply::success(__('messages.leadStatusChangeSuccess'));
    }

    /**
     * @param $leadID
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function followUpCreate($leadID)
    {
        $this->leadID = $leadID;
        return view('admin.lead.follow_up', $this->data);
    }

    /**
     * @param $leadID
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function contactCreate($leadID)
    {
        $this->leadID = $leadID;
        return view('admin.lead.contact', $this->data);
    }

    public function gdpr($leadID)
    {
        $this->lead = Lead::findOrFail($leadID);
        $this->allConsents = PurposeConsent::with(['lead' => function ($query) use ($leadID) {
            $query->where('lead_id', $leadID)
                ->orderBy('created_at', 'desc');
        }])->get();

        return view('admin.lead.gdpr.show', $this->data);
    }

    public function consentPurposeData($id)
    {
        $purpose = PurposeConsentLead::select('purpose_consent.name', 'purpose_consent_leads.created_at', 'purpose_consent_leads.status', 'purpose_consent_leads.ip', 'users.name as username', 'purpose_consent_leads.additional_description')
            ->join('purpose_consent', 'purpose_consent.id', '=', 'purpose_consent_leads.purpose_consent_id')
            ->leftJoin('users', 'purpose_consent_leads.updated_by_id', '=', 'users.id')
            ->where('purpose_consent_leads.lead_id', $id);

        return DataTables::of($purpose)
            ->editColumn('status', function ($row) {
                if ($row->status == 'agree') {
                    $status = __('modules.gdpr.optIn');
                } else if ($row->status == 'disagree') {
                    $status = __('modules.gdpr.optOut');
                } else {
                    $status = '';
                }

                return $status;
            })
            ->make(true);
    }

    public function saveConsentLeadData(SaveConsentLeadDataRequest $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $consent = PurposeConsent::findOrFail($request->consent_id);

        if ($request->consent_description && $request->consent_description != '') {
            $consent->description = $request->consent_description;
            $consent->save();
        }

        // Saving Consent Data
        $newConsentLead = new PurposeConsentLead();
        $newConsentLead->lead_id = $lead->id;
        $newConsentLead->purpose_consent_id = $consent->id;
        $newConsentLead->status = trim($request->status);
        $newConsentLead->ip = $request->ip();
        $newConsentLead->updated_by_id = $this->user->id;
        $newConsentLead->additional_description = $request->additional_description;
        $newConsentLead->save();

        $url = route('admin.leads.gdpr', $lead->id);

        return Reply::redirect($url);
    }

    /**
     * @param CommonRequest $request
     * @return array
     */
    public function followUpStore(\App\Http\Requests\FollowUp\StoreRequest $request)
    {

        $followUp = new LeadFollowUp();
        $followUp->lead_id = $request->lead_id;
        $followUp->next_follow_up_date = Carbon::createFromFormat($this->global->date_format, $request->next_follow_up_date)->format('Y-m-d');;
        $followUp->remark = $request->remark;
        $followUp->save();
        $this->lead = Lead::findOrFail($request->lead_id);

        $view = view('admin.lead.followup.task-list-ajax', $this->data)->render();

        return Reply::successWithData(__('messages.leadFollowUpAddedSuccess'), ['html' => $view]);
    }

    /**
     * @param CommonRequest $request
     * @return array
     */
    public function contactStore(\App\Http\Requests\Contact\StoreRequest $request)
    {
        $followUp = new LeadContact();
        $followUp->lead_id = $request->lead_id;
        $followUp->contact_date = Carbon::createFromFormat($this->global->date_format, $request->contact_date)->format('Y-m-d h:i:s');;
        $followUp->agent_id = Auth::user()->id;
        $followUp->save();
        $this->lead = Lead::findOrFail($request->lead_id);

        $url = route('admin.leads.index');

        return Reply::redirect($url);

        // $view = view('admin.lead.followup.task-list-ajax', $this->data)->render();

        // return Reply::successWithData(__('messages.leadFollowUpAddedSuccess'), ['html' => $view]);
    }

    public function followUpShow($leadID)
    {
        $this->leadID = $leadID;
        $this->lead = Lead::findOrFail($leadID);
        return view('admin.lead.followup.show', $this->data);
    }

    public function editFollow($id)
    {
        $this->follow = LeadFollowUp::findOrFail($id);
        $view = view('admin.lead.followup.edit', $this->data)->render();
        return Reply::dataOnly(['html' => $view]);
    }

    public function UpdateFollow(\App\Http\Requests\FollowUp\StoreRequest $request)
    {
        $followUp = LeadFollowUp::findOrFail($request->id);
        $followUp->lead_id = $request->lead_id;
        $followUp->next_follow_up_date = Carbon::createFromFormat($this->global->date_format, $request->next_follow_up_date)->format('Y-m-d');;
        $followUp->remark = $request->remark;
        $followUp->save();

        $this->lead = Lead::findOrFail($request->lead_id);

        $view = view('admin.lead.followup.task-list-ajax', $this->data)->render();

        return Reply::successWithData(__('messages.leadFollowUpUpdatedSuccess'), ['html' => $view]);
    }

    public function followUpSort(CommonRequest $request)
    {
        $leadId = $request->leadId;
        $this->sortBy = $request->sortBy;

        $this->lead = Lead::findOrFail($leadId);
        if ($request->sortBy == 'next_follow_up_date') {
            $order = "asc";
        } else {
            $order = "desc";
        }

        $follow = LeadFollowUp::where('lead_id', $leadId)->orderBy($request->sortBy, $order);


        $this->lead->follow = $follow->get();

        $view = view('admin.lead.followup.task-list-ajax', $this->data)->render();

        return Reply::successWithData(__('messages.followUpFilter'), ['html' => $view]);
    }


    public function export($followUp, $client)
    {
        $currentDate = Carbon::today()->format('Y-m-d');
        $lead = Lead::select('leads.id', 'client_name', 'website', 'client_email', 'company_name', 'lead_status.type as statusName', 'leads.created_at', 'lead_sources.type as source', \DB::raw("(select next_follow_up_date from lead_follow_up where lead_id = leads.id and leads.next_follow_up  = 'yes' and DATE(next_follow_up_date) >= {$currentDate} ORDER BY next_follow_up_date asc limit 1) as next_follow_up_date"))
            ->leftJoin('lead_status', 'lead_status.id', 'leads.status_id')
            ->leftJoin('lead_sources', 'lead_sources.id', 'leads.source_id');
        if ($followUp != 'all' && $followUp != '') {
            $lead = $lead->leftJoin('lead_follow_up', 'lead_follow_up.lead_id', 'leads.id')
                ->where('leads.next_follow_up', 'yes')
                ->where('lead_follow_up.next_follow_up_date', '<', $currentDate);
        }
        if ($client != 'all' && $client != '') {
            if ($client == 'lead') {
                $lead = $lead->whereNull('client_id');
            } else {
                $lead = $lead->whereNotNull('client_id');
            }
        }

        $lead = $lead->GroupBy('leads.id')->get();

        // Initialize the array which will be passed into the Excel
        // generator.
        $exportArray = [];

        // Define the Excel spreadsheet headers
        $exportArray[] = ['ID', 'Client Name', 'Website', 'Email', 'Company Name', 'Status', 'Created On', 'Source', 'Next Follow Up Date'];

        // Convert each member of the returned collection into an array,
        // and append it to the payments array.
        foreach ($lead as $row) {
            $exportArray[] = $row->toArray();
        }

        // Generate and return the spreadsheet
        Excel::create('leads', function ($excel) use ($exportArray) {

            // Set the spreadsheet title, creator, and description
            $excel->setTitle('Leads');
            $excel->setCreator('Adaptive')->setCompany($this->companyName);
            $excel->setDescription('leads file');

            // Build the spreadsheet, passing in the payments array
            $excel->sheet('sheet1', function ($sheet) use ($exportArray) {
                $sheet->fromArray($exportArray, null, 'A1', false, false);

                $sheet->row(1, function ($row) {

                    // call row manipulation methods
                    $row->setFont(array(
                        'bold'       =>  true
                    ));
                });
            });
        })->download('xlsx');
    }
}
