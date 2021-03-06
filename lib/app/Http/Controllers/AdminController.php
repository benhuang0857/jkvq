<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Order;
use App\Models\User;
use App\Models\Post;
use App\Models\Category;
use App\Mail\LevelUp;
use Illuminate\Support\Facades\Mail;
use Auth;
use Session;
use Cookie;
use PDF;

class AdminController extends Controller
{
    private $coutMem = 0;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $uid = Auth::user()->id;
        $users = User::where('id', $uid)->get();
        $categories = Category::all();
        $posts = Post::all();
        $tree = $this->treeView($users);

        $levelArray = Auth::user()->level;

        $arrForName = array();
        $arrForQty = array();
        $arrForCat = array();
        $result = array();

        $orders = Auth::user()->orders->where('pay',1);
        $orders->transform(function($order, $key){
            $order->cart = unserialize($order->cart);
            return $order;
        });
        foreach ($orders as $order)
        {
            foreach ($order->cart->items as $item)
            {
                
                if (empty($arrForName) || !in_array($item['item']['name'], $arrForName))
                {
                    array_push($arrForName, $item['item']['name']);
                    array_push($arrForQty, $item['qty']);
                }
                else if(in_array($item['item']['name'], $arrForName))
                {
                    $key = array_search($item['item']['name'], $arrForName);//找到商品在陣列中的位置
                    $arrForQty[$key] += $item['qty'];
                }
                array_push($arrForCat, $item['item']['category']);
            }
        }

        foreach ($arrForName as $id => $key)
        {
            $result[$key] = array(
                'name' => $arrForName[$id],
                'qty' => $arrForQty[$id],
                'category' => $arrForCat[$id],
            );
        }

        $data = [
            'USER' => Auth::user(),
            'OUTPUTS' => $this->coutMem,
            'LEVEL' => $levelArray,
            'RESULT' => $result,
            'CATEGORIES' => $categories,
            'POSTS' => $posts
        ];

        return view('back.admin')->with($data);
    }

    //更新資料頁面
    public function edit()
    {
        $user = Auth::user();

        $data = [
            'USER' => $user
        ];

        return view('back.admin-edit')->with($data);
    }

    //更新資料
    public function update(Request $request)
    {
        $user = Auth::user();

        if(request()->hasFile('AvatarImage'))
        {
            $avatar = request()->file('AvatarImage')->getClientOriginalName();
            $imageName = pathinfo($avatar, PATHINFO_FILENAME);
            $extension = request()->file('AvatarImage')->getClientOriginalExtension();
            $imageNametoStore = $imageName.'_'.time().'.'.$extension;
            $path = request()->file('AvatarImage')->storeAs('public/images/avatar', $imageNametoStore);
            Storage::delete('public/images/avatar/'.$user->image);
        }

        if($request->hasFile('AvatarImage')){
            $user->image = $imageNametoStore;
        }

        $user->name = $request->input('name');
        $user->nickname = $request->input('nickname');
        $user->email = $request->input('email');
        $user->phone = $request->input('phone');
        $user->address = $request->input('address');
        if(!empty($request->input('password')))
        {
            $user->password = bcrypt($request->input('password'));
        }
        
        $user->save();
        return redirect('/admin')->cookie('MSG', '資料更新成功', 0.05);
    }

    /**
     * Show the pdf contract.
     *
     * @return \Illuminate\Http\Response
     */
    public function show_pdf()
    {
        $user = Auth::user();
        $leader = User::where('id', $user->leader_id)->first();

        $pdf = PDF::loadView('back.contract', array('user' => $user, 'leader' => $leader));
        return $pdf->stream('contract.pdf');
    }
    //尋訪
    function treeView($Me)
    {
        $users = $Me;
        $tree = '<ul class="tree">';
        
        foreach ($users as $user)
        {
            if (Auth::user()->role != "一般會員")
            {
                $tree .= '<li><span><a href="/back/admin/members/'.$user->id.'" class="tree-name">
                <div class="image">
                    <img src="/back/storage/images/avatar/'.$user->image.'" class="img-circle elevation-2" style="width:50px;height:50px" alt="User Image">
                </div>
                </a>
                <p>姓名：'.$user->name.'<br>'.$user->nickname.'<br>'.$user->authorization_code.'</p>
                </span>';
            }
            else
            {
                $tree .= '<li><span><a href="#" class="tree-name">
                <div class="image">
                    <img src="/back/storage/images/avatar/'.$user->image.'" class="img-circle elevation-2" style="width:50px;height:50px" alt="User Image">
                </div>
                </a>
                <p>姓名：'.$user->name.'<br>'.$user->nickname.'<br>'.$user->authorization_code.'</p>
                
                </span>';
            }


            if(count($user->childs)) {
                $tree .=$this->childView($user);
            }
        }
        //$tree .= '<ul>';

        return $tree;
    }

    public function childView($user)
    {
        $html ='<ul>';
        foreach ($user->childs as $arr) {
            $this->coutMem += 1;
            if(count($arr->childs)){

                if (Auth::user()->role != "一般會員")
                {
                    $html .='<li><span><a href="/back/admin/members/'.$arr->id.'" class="tree-name">
                    <div class="image">
                    <img src="/back/storage/images/avatar/'.$arr->image.'" class="img-circle elevation-2" style="width:50px;height:50px" alt="User Image">
                    </div>
                    </a><p>姓名：'.$arr->name.'<br>'.$arr->nickname.'</br>'.$arr->authorization_code.'</p></span>';  
                }
                else
                {
                    $html .='<li><span><a href="#" class="tree-name">
                    <div class="image">
                    <img src="/back/storage/images/avatar/'.$arr->image.'" class="img-circle elevation-2" style="width:50px;height:50px" alt="User Image">
                    </div>
                    </a><p>姓名：'.$arr->name.'<br>'.$arr->nickname.'</br>'.$arr->authorization_code.'</p></span>';  
                }

                $html.= $this->childView($arr);

            }else{

                if (Auth::user()->role != "一般會員")
                {
                    $html .='<li><span><a href="/back/admin/members/'.$arr->id.'" class="tree-name">
                    <div class="image">
                    <img src="/back/storage/images/avatar/'.$arr->image.'" class="img-circle elevation-2" style="width:50px;height:50px" alt="User Image">
                    </div>
                    </a><p>姓名：'.$arr->name.'<br>'.$arr->nickname.'</br>'.$arr->authorization_code.'</p></span>';   
                }
                else
                {
                    $html .='<li><span><a href="#" class="tree-name">
                    <div class="image">
                    <img src="/back/storage/images/avatar/'.$arr->image.'" class="img-circle elevation-2" style="width:50px;height:50px" alt="User Image">
                    </div>
                    </a><p>姓名：'.$arr->name.'<br>'.$arr->nickname.'</br>'.$arr->authorization_code.'</p></span>';   
                }                   
                $html .="</li>";
            }             
        }
        $html .="</ul>";
        return $html;
    }

    //顯示線下會員
    public function ShowMembersPage()
    {
        $uid = Auth::user()->id;
        $users = User::where('id', $uid)->get();
        $tree = $this->treeView($users);

        $data = [
            'LEADER' => User::where('id', Auth::user()->leader_id)->first(),
            'USER' => Auth::user(),
            'TREE' => $tree
        ];
        return view('back.member.members')->with($data);
    }

    //顯示所有線下會員
    public function ShowAllMembersPage()
    {
        $uid = Auth::user()->id;
        $users = User::where('id', $uid)->get();
        $tree = $this->treeView($users);

        $data = [
            'USER' => Auth::user(),
            'OUTPUTS' => User::all(),
            'TREE' => $tree
        ];
        return view('back.member.showallmembers')->with($data);
    }

    //顯示所有線下會員
    public function SearchMember(Request $request)
    {
        //$request->get('searchQ');

        $members = User::where('name', 'like', '%'.$request->get('searchQ').'%')
        ->orWhere('email', 'like', '%'.$request->get('searchQ').'%')
        ->orWhere('phone', 'like', '%'.$request->get('searchQ').'%')
        ->orWhere('authorization_code', 'like', '%'.$request->get('searchQ').'%')
        ->get();

        return json_encode($members);
    }


    //顯示線下會員資料
    public function ShowYourMembersInfo($id)
    {
        $member = User::where('id', $id)->first();
        $leaders = User::where('id', '!=', $id)->get();

        $levelArray = $member->level;

        $data = [
            'MEM' => $member,
            'USER' => Auth::user(),
            'LEADERS' => $leaders,
            'LEVEL' => $levelArray,
        ];
        return view('back.member.member-edit')->with($data);
    }

    //更新線下會員資料
    public function UpdateYourMembersInfo($id, Request $request)
    {
        $user = User::where('id', $id)->first();

        if(request()->hasFile('AvatarImage'))
        {
            $avatar = request()->file('AvatarImage')->getClientOriginalName();
            $imageName = pathinfo($avatar, PATHINFO_FILENAME);
            $extension = request()->file('AvatarImage')->getClientOriginalExtension();
            $imageNametoStore = $imageName.'_'.time().'.'.$extension;
            $path = request()->file('AvatarImage')->storeAs('public/images/avatar', $imageNametoStore);
            Storage::delete('public/images/avatar/'.$user->image);
        }

        if($request->hasFile('AvatarImage')){
            $user->image = $imageNametoStore;
        }

        $user->name = $request->input('name');
        $user->nickname = $request->input('nickname');
        $user->leader_id = intval($request->input('leader_id'));
        $user->email = $request->input('email');
        $user->phone = $request->input('phone');
        $user->address = $request->input('address');
        $user->authorization_code = $request->input('authorization_code');
        $user->contract_url = $request->input('contract_url');
        $user->role = $request->input('role');
        $user->milage = $request->input('milage');
        $user->remarks = $request->input('remarks');

        $categories = Category::all();
        $mergeArray = array();

        foreach($categories as $category)
        {
            $data = $request->input("$category->name");
            $jsonArray = array( "$category->name" => "$data");

            if($mergeArray != NULL)
            {
                $mergeArray = array_merge($jsonArray, $mergeArray);
            }
            else
            {
                $mergeArray = $jsonArray; 
            }   
        }
        $user->level = json_encode($mergeArray,JSON_UNESCAPED_UNICODE);

        if(!empty($request->input('password')))
        {
            $user->password = bcrypt($request->input('password'));
        }
        $user->save();
        return redirect('/admin');
    }

    //刪除會員
    public function DeleteYourMember($id)
    {
        $member = User::where('id',$id)->first();
        $leader_id = $member->leader_id;
        $this->SetMemberLeaderId($id, $leader_id);
        $member->delete();
        
        return redirect('/admin');
    }

    //刪除後自動設定領導ID
    public function SetMemberLeaderId($id, $leader_id)
    {
        $link_leader_id = $leader_id;
        $members = User::where('leader_id', $id)->get();

        foreach($members as $member)
        {
            $member->leader_id = $link_leader_id;
            $member->save();
        }
    }

    public function levelUp($key, $value)
    {
        $catName = $key;
        $level = $value;

        $nextLevel;

        if($level == 'F')$nextLevel = 'E';
        if($level == 'E')$nextLevel = 'D';
        if($level == 'D')$nextLevel = 'C';
        if($level == 'C')$nextLevel = 'B';
        if($level == 'B')$nextLevel = 'A';
        if($level == 'A')$nextLevel = 'A';

        $user = Auth::user();
        $levelArray = json_decode($user->level, true);
        $levelArray[$catName] = $nextLevel;

        $user->level = json_encode($levelArray,JSON_UNESCAPED_UNICODE);

        $user->save();

        //Mail::to('benhuang810406@gmail.com')->send(new LevelUp($params));

        return redirect('/admin');
    }
}
