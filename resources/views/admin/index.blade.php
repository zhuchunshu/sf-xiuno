@extends('app')

@section('title','Xiuno迁移')

@section('content')

    <div class="col-md-6">
        <div class="card card-body">
            <h3 class="card-title">Xiuno设置</h3>
            <form action="" method="post">
                <x-csrf/>
                <div class="mb-3">
                    <label for="inputEmail">Xiuno安装路径</label>
                    <input type="text" class="form-control" id="xiuno_path" name="xiuno_path"
                           value="{{get_options('xiuno_path')}}">
                </div>
                <button class="btn btn-primary">保存</button>
            </form>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-body">
            <h3 class="card-title">状态检测</h3>
            @if($status['_conf'])
                <div class="mb-3">
                    <span class="status status-green">
                        <span class="status-dot status-dot-animated"></span>
                        Xiuno配置信息读取成功!
                    </span>
                </div>
            @else
                <div class="mb-3">
                    <span class="status status-red">
                        <span class="status-dot status-dot-animated"></span>
                        Xiuno配置信息读取失败!
                    </span>
                </div>
            @endif
            @if($status['_database'])
                <div class="mb-3">
                    <span class="status status-green">
                        <span class="status-dot status-dot-animated"></span>
                        数据库信息读取成功!
                    </span>
                </div>
            @else
                <div class="mb-3">
                    <span class="status status-red">
                        <span class="status-dot status-dot-animated"></span>
                        数据库信息读取失败!
                    </span>
                </div>
            @endif
            @if($status['_database'])
                <div class="mb-3">
                    <span class="status status-green">
                        <span class="status-dot status-dot-animated"></span>
                        upload文件读取成功!
                    </span>
                </div>
            @else
                <div class="mb-3">
                    <span class="status status-red">
                        <span class="status-dot status-dot-animated"></span>
                        upload文件读取失败!
                    </span>
                </div>
            @endif
            <form action="/admin/xiuno/migrate" method="post">
                <x-csrf/>
                <button class="btn btn-primary">开始迁移</button>
            </form>
        </div>
    </div>

    @if($status['_database'])
        <div class="col-md-6">
            <div class="card card-body">
                <h3 class="card-title">数据库信息</h3>
                <div>
                    <div class="mb-3">
                        <label class="form-label">host</label>
                        <input type="text" class="form-control disabled" disabled value="{{$database['host']}}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">user</label>
                        <input type="text" class="form-control disabled" disabled value="{{$database['user']}}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">name</label>
                        <input type="text" class="form-control disabled" disabled value="{{$database['name']}}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">password</label>
                        <input type="text" class="form-control disabled" disabled value="{{$database['password']}}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">tablepre</label>
                        <input type="text" class="form-control disabled" disabled value="{{$database['tablepre']}}">
                    </div>
                </div>
            </div>
        </div>
    @endif

@endsection