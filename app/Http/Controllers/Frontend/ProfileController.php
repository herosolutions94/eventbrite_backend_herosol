<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function getUserProfile(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if(empty($user->firstname) && empty($user->lastname)){
            $nameArray = explode(" ", $user->name);
            $user->firstname=$nameArray[0];
            $user->lastname=count($nameArray) > 1 ? $nameArray[1] : "";
        }
        return response()->json(['data' => $user], 200);
    }
    public function upload_image(Request $request){
        $res=array();
        $res['status']=0;
            $input = $request->all();
            $res['input']=$input;
            if ($request->hasFile('image')) {
                $type="uploads";
                $res['type']='public/'.$type.'/';
                $request_data = [
                    'image' => 'mimes:png,jpg,jpeg,svg,gif|max:40000'
                ];
                $validator = Validator::make($input, $request_data);
                // json is null
                if ($validator->fails()) {
                    $res['status']=0;
                    $res['msg']='Error >>'.$validator->errors()->first();
                }
                else{
                    $image=$request->file('image')->store('public/'.$type.'/');
                    // print_r($image);die;
                    $res['image']=$image;
                    if(!empty(basename($image))){
                        $res['status']=1;
                        $res['image_name']=basename($image);
                        // $res['image_path']=storage_path('app/public/'.basename($image));
                    }
                    else{
                        $res['msg']="Something went wrong while uploading image. Please try again!";
                    }
                }


            }
            else{
                $res['image']="Only images are allowed to upload!";
            }

        exit(json_encode($res));
    }
    public function updateUserProfile(Request $request)
    {
        $data = $request->all();
        try{
            if($request->input('type', null)=='player'){
                $validator = Validator::make($data, [
                    'firstname' => 'string',
                    'lastname' => 'string',
                    'phone_number' => 'string',
                    'country' => 'string',
                    'city' => 'string',
                    'state'=>'string',
                    'postal_code' => 'string',
                    'address' => 'string',
                    'gender' => 'string',
                    // 'dob' => 'string',
                ]);
            }
            else{
                $validator = Validator::make($data, [
                    'name' => 'string',
                    'firstname' => 'string',
                    'lastname' => 'string',
                    'phone_number' => 'string',
                    'email' => 'email',
                    'org_name' => 'string',
                    'org_website' => 'string',
                    'org_mailing_address' => 'string',
                    'org_communication_method' => 'string',
                    'org_timezone' => 'string',
                    'country' => 'string',
                    'city' => 'string',
                    'state'=>'string',
                    'facebook' => 'string',
                    'twitter' => 'string',
                    'instagram' => 'string',
                    'linkedIn' => 'string',
                    'secondary_phone' => 'string',
                    'secondary_email' => 'email',
                    'postal_code' => 'string',
                    'address' => 'string',
                ]);
            }
            
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }

        }catch(\Exception $e){
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $data['dob']=!empty($data['dob']) ? date("Y-m-d",strtotime($data['dob'])) : null;
        unset($data['type']);
        $user = User::where('id', $data['id'])->update($data);
        return response()->json(['data' => $user, 'message' => 'Profile updated successfully'], 200);
    }
}
