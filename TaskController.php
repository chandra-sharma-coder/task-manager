<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Validator;
use Mail;
use Session;
use File;

class TaskController extends Controller
{
    
    public function login(Request $request)
    {
        $valid = Validator::make($request -> all(),[
            'email' => 'required|email',
            'password' => 'required',
        ]);
      
        if (!$valid -> passes()) {
            return response() -> json(['status' => 'error',
            'error' => $valid -> errors()]);
        }else{
            $email = $request -> email;
            $password = $request -> password;
            $user = User::where('email', $email) -> first();
            if ($user) {
                if (Hash::check($password, $user -> password)) {
                    Session::put('USER_LOGIN', true);
                    Session::put('user_id', $user -> id);
                    Session::put('user_name', $user -> name);
                    return response() -> json(['status' => 'success','message' => 'Login Successfully!']);
                }else{
                    return response() -> json(['status' => 'error',
                    'message' => 'Password is incorrect!']);
                }
            }else{
                return response() -> json(['status' => 'error',
                'message' => 'Email or Password is incorrect!']);
            }
        }
    }

    public function dashboard()
    {
        $tasks = Task::join('users as aby','aby.id','tasks.asigned_by')
        ->join('users as ato','ato.id','tasks.asigned_to')
        ->where('asigned_by',Session::get('user_id'))
        ->orWhere('asigned_to',Session::get('user_id'))
        ->orderBy('tasks.id','desc')
        ->get(['tasks.*','aby.name as asigned_by_name','ato.name as asigned_to_name']);
        return view('dashboard')->with('tasks',$tasks)->with('users',User::where('id','!=',Session::get('user_id'))->get(['id','name']));
    }

    public function registration()
    {
        return view('registration');
    }

    public function create_user(Request $request)
    {
        $valid = Validator::make($request -> all(),[
            'name' => 'required',
            'email' => 'required|email',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
        ]);

        if (!$valid -> passes()) {
            return response() -> json(['status' => 'error',
            'error' => $valid -> errors()]);
        }else{
            $res = new User;
            $res -> name = $request -> name;
            $res -> email = $request -> email;
            $res -> password = Hash::make($request -> password);
            if($res -> save()){
                return response() -> json(['status' => 'success',
                'message' => 'User created successfully!']);
            }else{
                return response() -> json(['status' => 'error',
                'message' => 'Something went wrong!']);
            }
        }
    }

    public function create_task(Request $request)
    {
        // return $request;
        $valid = Validator::make($request -> all(),[
            'title' => 'required',
            'pid' => 'numeric|nullable',
            'deadline' => 'required',
            'description' => 'required',
            'file' => 'required_if:pid,null|mimes:jpeg,png,jpg,gif,svg,pdf,doc,docx,xls,xlsx,ppt,pptx|max:2048',
            'status' => 'required|not_in:0',
            'asigned_to' => 'required|not_in:0',

        ]);
        

        if (!$valid -> passes()) {
            return response() -> json(['status' => 'error',
            'error' => $valid -> errors()]);
        }else{
            $file = $request -> file('file');
            $destinationPath = 'uploads';
            if($request->pid != ''){
                $res = Task::find($request->pid);
                if(isset($file)){
                    $file_name = $destinationPath.'/'.time().'.'.$file -> getClientOriginalExtension();
                    $file -> move($destinationPath,$file_name);
                }else{
                    $file_name = $res->file;
                }
            }else{
                $res = new Task;
                // return $request -> title;
                if(isset($file)){
                    $file_name = $destinationPath.'/'.time().'.'.$file -> getClientOriginalExtension();
                    $file -> move($destinationPath,$file_name);
                }else{
                    $file_name = '-';
                }
            }
           
            // return $request;
            $res -> title = $request -> title;
            $res -> description = $request -> description;
            $res -> deadline = $request -> deadline;
            $res -> asigned_on = date('Y-m-d');
            $res -> file = $file_name;
            $res -> status = $request -> status;
            $res -> asigned_by = Session::get('user_id');
            $res -> asigned_to = $request -> asigned_to;
            if($res -> save()){
                $tasks = Task::join('users as aby','aby.id','tasks.asigned_by')
                ->join('users as ato','ato.id','tasks.asigned_to')
                ->where('asigned_by',Session::get('user_id'))
                ->orWhere('asigned_to',Session::get('user_id'))
                ->orderBy('tasks.id','desc')
                ->get(['tasks.*','aby.name as asigned_by_name','ato.name as asigned_to_name']);
                return response() -> json(['status' => 'success',
                'message' => 'Task created successfully!','tasks' => $tasks]);
            }else{
                return response() -> json(['status' => 'error',
                'message' => 'Something went wrong!']);
            }
        }
    }

    public function delete_task(Request $request)
    {
        $file=Task::where('id',$request->id)->get('file');
        $task_id = $request->id;
        $task = Task::destroy($task_id);
        if($task){
            //code for delete the file from storage
           File::delete($file[0]->file);

            $tasks = Task::join('users as aby','aby.id','tasks.asigned_by')
                ->join('users as ato','ato.id','tasks.asigned_to')
                ->where('asigned_by',Session::get('user_id'))
                ->orWhere('asigned_to',Session::get('user_id'))
                ->orderBy('tasks.id','desc')
                ->get(['tasks.*','aby.name as asigned_by_name','ato.name as asigned_to_name']);
            return response() -> json(['status' => 'success',
            'message' => 'Task deleted successfully!','tasks' => $tasks]);
        }else{
            return response() -> json(['status' => 'error',
            'message' => 'Something went wrong!']);
        }
    }
  
    public function get_details(Request $request)
    {
        return response()->json(['user'=>User::where('id','!=',Session::get('user_id'))->get(['id','name']),'task'=>Task::find($request->id)]);
    }
  
    public function sort_task(Request $request)
    {
        $tasks = Task::join('users as aby','aby.id','tasks.asigned_by')
                ->join('users as ato','ato.id','tasks.asigned_to')
                ->where('tasks.status',$request->val)
                ->where('asigned_by',Session::get('user_id'))
                ->orWhere('asigned_to',Session::get('user_id'))
                ->orderBy('tasks.id','desc')
                ->get(['tasks.*','aby.name as asigned_by_name','ato.name as asigned_to_name']);
        return response() -> json(['status' => 'success','task' => $tasks]);
    }
}
