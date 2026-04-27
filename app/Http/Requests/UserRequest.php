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

        return [
                'username'          => ['required', 'max:255', $uniqueUsername],
                'email'             => ['required', 'email', 'max:255', $uniqueEmail],
                'contact_number'    => ['nullable', $uniqueContact],
                'profile_image'     => 'mimetypes:image/jpeg,image/png,image/jpg,image/gif',
        ];
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
