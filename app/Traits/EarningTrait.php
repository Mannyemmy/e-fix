<?php

namespace App\Traits;

use App\Models\User;
use App\Models\ProviderPayout;


trait EarningTrait {

    public function get_provider_commission($bookings,$provider){
      

        $total_amount=$bookings->sum('total_amount');

        $provider_earning=$this->getProviderCommission($bookings);

        $provider_paid_earning = ProviderPayout::where('provider_id',$provider->id)->sum('amount') ?? 0;

        $handyman_earnings=$this->getHandymanCommission($bookings);

        $data=[
             'total_earning'=>$total_amount,
             'provider_total_earning'=> $provider_earning,
             'provider_paid_earning'=>  $provider_paid_earning,
             'provider_due_earning'=>$provider_earning - $provider_paid_earning,
             'admin_earning'=>$total_amount-$handyman_earnings-$provider_earning,
             
        ];

        return $data;
    }

    public function getProviderCommission($bookings)
    {
        $providerEarning = 0;
    
        foreach ($bookings as $booking) {
            $commissionData = json_decode($booking->commission_data, false);
    
            if ($commissionData) {
                if ($commissionData->type == 'percent') {
                    $providerEarning += ($booking->total_amount * $commissionData->commission) / 100;
                } else {
                    $providerEarning += $commissionData->commission;
                }
            }
        }
    
        return $providerEarning;
    }


    public function getHandymanCommission($bookings)
    {
        $handymanEarning = 0;
    
        foreach ($bookings as $booking) {

            $providerId = $booking->provider_id;

            $handyman = $booking->handymanAdded()->where('handyman_id', '!=', $providerId)->get();
  
                foreach ($handyman as $handyman) {

                    $commissionData = json_decode($handyman->commission_data, false);

                    if( $commissionData){

                        if ($commissionData->type == 'percent') {

                            $handymanEarning += ($booking->total_amount * $commissionData->commission) / 100;

                        }else{

                            $handymanEarning += $commissionData->commission;

                        }  

                    }
                }
           }

        return $handymanEarning;
    }


    public function getProviderBookingCommission($booking, $payment)
    {
        $provider_commission_data = [];
        $providerEarning = 0;
    
        $provider = User::where('id', $booking['provider_id'])->with('providertype')->first();
        $provider_commission = $provider->providertype ? json_encode($provider->providertype) : null;
        $commissionData = json_decode($provider_commission, false);
    
        if ($commissionData) {
            if ($commissionData->type === 'percent') {
                $providerEarning += ($booking->total_amount * $commissionData->commission) / 100;
            } else {
                $providerEarning += $commissionData->commission;
            }
        }
    
        $provider_commission_data = [
            'employee_id'       => $booking->provider_id,
            'booking_id'        => $booking->id,
            'user_type'         => 'provider',
            'commission_amount' => $providerEarning,
            'commission_status' => $payment->payment_status === 'paid' ? 'unpaid' : 'pending',
            'commissions'       => $provider_commission
        ];
    
        return $provider_commission_data;
    }
    

    public function getHandymanBookingCommission($booking, $payment)
    {
        $handyman_commission_data = [];
        $handymanEarning = 0;
    
        $providerId = $booking->provider_id;
        $handymen = $booking->handymanAdded()->where('handyman_id', '!=', $providerId)->get();

    
        foreach ($handymen as $handyman) {
            $handymanData = User::where('id', $handyman->handyman_id)->with('handymantype')->first();

            $handyman_commission = $handymanData->handymantype ? json_encode($handymanData->handymantype) : null;
            $commissionData = json_decode($handyman_commission, false);
    
            if ($commissionData) {
                if ($commissionData->type === 'percent') {
                    $handymanEarning += ($booking->total_amount * $commissionData->commission) / 100;
                } else {
                    $handymanEarning += $commissionData->commission;
                }
            }
    
            $handyman_commission_data[] = [
                'employee_id'       => $handyman->handyman_id,
                'booking_id'        => $booking->id,
                'user_type'         => 'handyman',
                'commission_amount' => $handymanEarning,
                'commission_status' => $payment->payment_status === 'paid' ? 'unpaid' : 'pending',
                'commissions'       => $handyman_commission
            ];

    
        }
    
        return $handyman_commission_data;
    }
    

}



    

?>