@extends('layouts.backlayout')

@section('content')
    <!-- Content Wrapper. Contains page content -->
    <div class="table-page segments-page">
        <div class="card">
            <!-- /.card-header -->
            <div class="card-body p-0" style="display: block;">
                <div class="table-responsive">
                    <table class="table m-0" style="min-width:600px">
                    <thead>
                    <tr>
                        <th>授權碼</th>
                        <th>姓名</th>
                        <th>Email</th>
                        <th>電話</th>
                        <th>里程數</th>
                        <th>動作</th>
                    </tr>
                    </thead>
                    <tbody id="dynamic-row">
                    @foreach ($OUTPUTS as $OUTPUT)
                        @if ($OUTPUT->id != 0)
                        <tr>
                            <th scope="row">{{$OUTPUT->authorization_code}}</th>
                            <td>{{$OUTPUT->name}}</td>
                            <td>{{$OUTPUT->email}}</td>
                            <td>{{$OUTPUT->phone}}</td>
                            <td>{{$OUTPUT->milage}}</td>
                            <th>
                                <a class="btn btn-primary btn-sm" style="margin-bottom:5px; display:block; width:80px" href="{{url('/admin/members/'.$OUTPUT->id.'')}}">編輯</a>
                                <a href="#" class="btn btn-danger btn-sm" style="margin-bottom:5px; display:block; width:80px" onclick="callAjax({{$OUTPUT->id}})">刪除</a>
                            </th>
                        </tr>
                        @endif
                        
                    @endforeach
                    </tbody>
                    </table>
                </div>
                <!-- /.table-responsive -->
            </div>
        </div>
    </div>
    <!-- /.content-wrapper -->
    <script type="text/javascript">
        function callAjax(articleId) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            })

            $.ajax({
                type: 'POST',
                url: '/back/admin/members/'+articleId+'/delete',
                success:function(res){
                    window.location.href = '/back/admin/members';
                }
            })
        }
    </script>
@endsection
