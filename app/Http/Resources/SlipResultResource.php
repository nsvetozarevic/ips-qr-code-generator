<?php

namespace App\Http\Resources;

use App\Data\SlipResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SlipResult */
class SlipResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payer' => $this->parsed->payer,
            'purpose' => $this->parsed->purpose,
            'payee' => $this->parsed->payee,
            'amount' => $this->parsed->amount,
            'currency' => $this->parsed->currency,
            'payerAccount' => $this->parsed->payerAccount,
            'recipientAccount' => $this->parsed->recipientAccount,
            'paymentCode' => $this->parsed->paymentCode,
            'debtorModel' => $this->parsed->debtorModel,
            'debtorReferenceNumber' => $this->parsed->debtorReferenceNumber,
            'creditorModel' => $this->parsed->creditorModel,
            'creditorReferenceNumber' => $this->parsed->creditorReferenceNumber,
            'ipsQrData' => $this->ipsQrData,
        ];
    }
}
