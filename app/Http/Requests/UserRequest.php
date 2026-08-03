<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $id       = request()->id;
        $userType = request()->user_type;

        // register() defaults an omitted user_type to 'user', and the user app never
        // sends one. Mirror that here, otherwise the uniqueness rules below fall back
        // to matching across every user_type and a customer signing up with the same
        // phone number as an existing provider would be rejected.
        if (empty($userType) && $this->isPublicRegistration()) {
            $userType = 'user';
        }

        // When user_type is provided (registration / admin save), scope uniqueness to that
        // user_type so the same email/username can exist across different roles.
        $uniqueEmail    = Rule::unique('users', 'email')->ignore($id);
        $uniqueUsername = Rule::unique('users', 'username')->ignore($id);
        $uniqueContact  = Rule::unique('users', 'contact_number')->ignore($id);

        if (!empty($userType)) {
            $uniqueEmail    = $uniqueEmail->where('user_type', $userType);
            $uniqueUsername = $uniqueUsername->where('user_type', $userType);
            $uniqueContact  = $uniqueContact->where('user_type', $userType);
        }

        $rules = [
                'username'          => ['required', 'max:255', $uniqueUsername],
                'email'             => ['required', 'email', 'max:255', $uniqueEmail],
                'contact_number'    => ['nullable', $uniqueContact],
                'profile_image'     => 'mimetypes:image/jpeg,image/png,image/jpg,image/gif',
        ];

        // This form request is shared by the admin CRUD screens, profile update and the
        // public API. Only the unauthenticated self-service endpoint (POST /api/register)
        // gets the stricter rules below, so the admin/profile flows keep working as before.
        if ($this->isPublicRegistration()) {
            // register() reads these three straight off the input array, so a missing
            // value used to surface as a 500 rather than a validation error.
            $rules['first_name'] = ['required', 'string', 'max:100'];
            $rules['last_name']  = ['required', 'string', 'max:100'];
            // min:6 matches passwordLengthGlobal in the mobile apps - do not raise this
            // without raising it there too, or app-side signups will pass client
            // validation and then be rejected by the server.
            $rules['password']   = ['required', 'string', 'min:6', 'max:255'];
            // Was completely unvalidated and taken straight from the request body,
            // which let anyone pick their own role. Left nullable because the user app
            // omits it and the controller defaults it to 'user'.
            $rules['user_type']  = ['nullable', Rule::in(['user', 'provider', 'handyman'])];

            // Bots were mass-creating provider accounts with no phone number at all.
            // Real providers always supply one, so require it for those roles. Customer
            // signups stay optional because the user app does not collect a phone.
            if (in_array($userType, ['provider', 'handyman'], true)) {
                $rules['contact_number'] = [
                    'required', 'string', 'min:7', 'max:20',
                    'regex:/^\+?[0-9][0-9\s\-()]*$/',
                    $uniqueContact,
                ];
            }
        }

        return $rules;
    }

    /**
     * Is this the unauthenticated public signup endpoint?
     */
    protected function isPublicRegistration()
    {
        return $this->isMethod('post') && $this->is('api/register');
    }

    public function messages()
    {
        return [
           'profile_image.*' => __('messages.image_png_gif')
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        if ( request()->is('api*')){
            $data = [
                'status' => false,
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];

            throw new HttpResponseException(response()->json($data,406));
        }

        throw new HttpResponseException(redirect()->back()->withInput()->with('errors', $validator->errors()));
    }
}
