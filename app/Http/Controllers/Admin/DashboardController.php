<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class DashboardController extends Controller
{
    public function index()
    {
        // cal php artisan storage:link
        // dd(Artisan::call('key:generate');
    
        // dd(Artisan::call('cache:clear'));
        // dd(Artisan::call('storage:link'));
        // exec('php artisan storage:link');
        // shall_exec('php artisan storage:link');

         // Execute the storage:link command
        Artisan::call('cache:clear');

        // Check the command output (optional)
        // $output = Artisan::output();
        // dd($output);
        
        // You can also check the exit code to see if it was successful
        // $exitCode = Artisan::output();
        
        // dd("here");
        return view('admin.dashboard.index');
    }
     public function profile(Request $request , $id)
    {
        $data['user'] = User::find($id);
        return view('admin.dashboard.profile',$data);
    }
    public function updateProfile(Request $request, User $user,$id)
    {
        // dd($request->all());
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'phone' => 'required',
        ]);
        if(isset($request->password) && $request->password != " "){
            $request->validate([
            'password' => 'required|min:6',
            'confirm_password' => 'required|min:6|max:20|same:password',
            ]);    
            $userData['password'] = bcrypt($request->password);
        }
        $userData['name']  =  $request->name;
        $userData['phone_number']   =  $request->phone;
        $userData['email']      =  $request->email;
        $userData['status']      =  $request->status;
        $userData['org_name']      =  $request->org_name ?? null;
        $userData['org_website']      =  $request->org_website ?? null;
        $userData['org_mailing_address']      =  $request->org_mailing_address ?? null;
        $userData['org_communication_method']      =  $request->org_communication_method ?? null;
        $userData['org_timezone']      =  $request->org_timezone ?? null;
        $userData['country']      =  $request->country ?? null;
        $userData['city']      =  $request->city ?? null;
        $userData['postal_code']      =  $request->postal_code ?? null;
        $userData['address']      =  $request->address ?? null;
        
        User::where('id',$id)->update($userData);
        return redirect()->back()->with('flash_message_success', 'Updated successfully');
    }
    public function forgetPassword()
    {
        return view('admin.auth.forget_password');
    }
    public function verifyCode()
    {
        return view('admin.auth.verify_code');
    }
    public function updatePassword()
    {
        return view('admin.auth.change_password');
    }
}
