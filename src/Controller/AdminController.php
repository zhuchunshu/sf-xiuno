<?php

namespace App\Plugins\Xiuno\src\Controller;

use App\Middleware\AdminMiddleware;
use App\Model\AdminOption;
use App\Plugins\Comment\src\Model\TopicComment;
use App\Plugins\Topic\src\Models\Topic;
use App\Plugins\Topic\src\Models\TopicTag;
use App\Plugins\User\src\Models\User;
use App\Plugins\User\src\Models\UserClass;
use App\Plugins\User\src\Models\UsersOption;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\Utils\Str;
use Swoole\Coroutine\MySQL;
use Swoole\Coroutine\System;

#[Middleware(AdminMiddleware::class)]
#[Controller(prefix: '/admin/xiuno')]
class AdminController
{
	#[GetMapping(path: "")]
	public function index()
	{
		// read conf status
		$_conf = file_exists(get_options('xiuno_path') . '/conf/conf.php');
		$_upload = is_dir(get_options('xiuno_path') . '/upload');
		$_database = false;
		if($_conf) {
			$conf = include get_options('xiuno_path') . '/conf/conf.php';
			if(isset($conf['db']['mysql']['master']) && $conf['db']['mysql']['master']) {
				$_database = true;
			}
			$database = $conf['db']['mysql']['master'];
		}
		$status = [
			'_conf' => $_conf,
			'_database' => $_database,
			'_upload' => $_upload,
		];
		$migrate = false;
		if($_conf && $_database && $_upload) {
			$migrate = true;
		}
		return view("Xiuno::admin.index", ['status' => $status, 'database' => $database, 'migrate' => $migrate]);
	}
	
	#[PostMapping(path: "")]
	public function store()
	{
		$this->setOption([
			'xiuno_path' => request()->input('xiuno_path'),
		]);
		return redirect()->with('success', '保存成功')->url('/admin/xiuno')->go();
	}
	
	private function setOption($data = []): void
	{
		foreach($data as $key => $value) {
			if(AdminOption::query()->where("name", $key)->exists()) {
				AdminOption::query()->where("name", $key)->update(['value' => $value]);
			} else {
				AdminOption::query()->create(['name' => $key, 'value' => $value]);
			}
		}
		options_clear();
	}
	
	#[PostMapping(path: "migrate")]
	public function migrate()
	{
		// read conf status
		$_conf = file_exists(get_options('xiuno_path') . '/conf/conf.php');
		$_upload = is_dir(get_options('xiuno_path') . '/upload');
		$_database = false;
		if($_conf) {
			$conf = include get_options('xiuno_path') . '/conf/conf.php';
			if(isset($conf['db']['mysql']['master']) && $conf['db']['mysql']['master']) {
				$_database = true;
			}
		}
		if(!$_conf || !$_database || !$_upload) {
			return redirect()->with('danger', '迁移条件不满足')->url('/admin/xiuno')->go();
		}
		// 迁移条件满足
		if(!is_dir(public_path("plugins/Xiuno"))) {
			System::exec("mkdir " . public_path("plugins/Xiuno"));
		}
		if(!is_dir(public_path("plugins/Xiuno/upload"))) {
			System::exec("mkdir " . public_path("plugins/Xiuno/upload"));
		}
		// copy xiuno upload to public/plugins/Xiuno/upload
		copy_dir(get_options('xiuno_path') . '/upload', public_path("plugins/Xiuno/upload"));
		// database migrate
		$mysql = new MySQL();
		$mysql->connect([
			'host' => $conf['db']['mysql']['master']['host'],
			'user' => $conf['db']['mysql']['master']['user'],
			'password' => $conf['db']['mysql']['master']['password'],
			'database' => $conf['db']['mysql']['master']['name'],
		]);
		if($mysql->connected === false) {
			return redirect()->with('danger', 'xiuno数据库连接失败')->url('/admin/xiuno')->go();
		}
		// 迁移用户组
		$this->migrate_group($mysql, $conf['db']['mysql']['master']['tablepre']);
		// 迁移用户
		$this->migrate_user($mysql, $conf['db']['mysql']['master']['tablepre']);
		// 迁移tag
		$this->migrate_forum($mysql, $conf['db']['mysql']['master']['tablepre']);
		// 迁移帖子
		$this->migrate_thread($mysql, $conf['db']['mysql']['master']['tablepre']);
		// 迁移评论
		$this->migrate_comment($mysql, $conf['db']['mysql']['master']['tablepre']);
		return redirect()->url('/admin/xiuno')->with('success','迁移成功!')->go();
	}
	
	private function migrate_group(Mysql $mysql, $prefix): void
	{
		$sql = "SELECT * FROM {$prefix}group";
		foreach($mysql->query($sql) as $data) {
			if(!UserClass::query()->where('name', $data['name'])->exists()) {
				UserClass::query()->create([
					'name' => $data['name'],
					'color' => '#206bc4',
					'icon' => '',
					'quanxian' => '["comment_caina","comment_create","comment_edit","comment_remove","report_comment","report_topic","topic_create","topic_delete","topic_edit"]',
					'permission-value' => 1,
				]);
			}
		}
	}
	
	private function migrate_user(MySQL $mysql, mixed $tablepre)
	{
		$sql = "SELECT * FROM {$tablepre}user";
		foreach($mysql->query($sql) as $user) {
			if(!User::query()->where('username', $user['username'])->orWhere('email', $user['email'])->exists()) {
				// 创建用户
				// group_id
				$sql_group_name = "SELECT * FROM {$tablepre}group WHERE gid='{$user['gid']}' LIMIT 1";
				// group name
				$group_name = $mysql->query($sql_group_name)[0]['name'];
				$group_id = 1;
				if(UserClass::query()->where('name', $group_name)->exists()) {
					$group_id = UserClass::query()->where('name', $group_name)->value('id');
				}
				$userOption = UsersOption::query()->create([
					"qianming" => "这个人没有签名",
					"qq" => $user['qq'],
					"credits" => $user['credits'],
					'golds' => $user['golds'],
					'money' => $user['rmbs'],
				]);
				$avatar = null;
				if($user['avatar']>0){
					$avatar = "/plugins/Xiuno/upload/avatar/000/".$user['uid'].".png";
				}
				User::query()->create([
					'username' => $user['username'],
					'email' => $user['email'],
					'password' => $user['password'],
					'avatar' => $avatar,
					'class_id' => $group_id,
					'_token' => Str::random(),
					'options_id' => $userOption->id
				]);
			}
		}
	}
	
	private function migrate_forum(MySQL $mysql, mixed $tablepre)
	{
		$sql = "SELECT * FROM {$tablepre}forum";
		foreach ($mysql->query($sql) as $forum){
			//https://ui-avatars.com/api/?background=random&format=svg&name=cpuer
			if(!TopicTag::query()->where('name',$forum['name'])->exists()){
				TopicTag::query()->create([
					'name' => $forum['name'],
					'description' => $forum['brief'],
					'color' => '#000000',
					'icon' => 'https://ui-avatars.com/api/?background=random&format=svg&name='.$forum['name'],
				]);
			}
		}
	}
	
	private function migrate_thread(MySQL $mysql, mixed $tablepre)
	{
		$sql = "SELECT * FROM {$tablepre}thread";
		foreach ($mysql->query($sql) as $thread){
			if(!Topic::query()->where('title',$thread['subject'])->exists()){
				// forum id
				$sql_forum_name = "SELECT * FROM {$tablepre}forum WHERE fid='{$thread['fid']}' LIMIT 1";
				// forum name
				$forum_name = $mysql->query($sql_forum_name)[0]['name'];
				// tag_id
				$tag_id = 1;
				if(TopicTag::query()->where('name', $forum_name)->exists()) {
					$tag_id = TopicTag::query()->where('name', $forum_name)->value('id');
				}
				// title
				$title = $thread['subject'];
				// get user id
				$sql_user = "SELECT * FROM {$tablepre}user WHERE uid='{$thread['uid']}'";
				$user = $mysql->query($sql_user)[0];
				$user_id = 0;
				if(User::query()->where(['email'=>$user['email']])->orWhere(['username'=>$user['username']])->exists()){
					$user_id = User::query()->where(['email'=>$user['email']])->value('id');
				}
				// view
				$view = $thread['views'];
				// content
				$sql_post = "SELECT * FROM {$tablepre}post WHERE tid = '{$thread['tid']}' AND isfirst='1'";
				$post = $mysql->query($sql_post)[0];
				$converter = new \Markdownify\Converter;
				$content = $post['message_fmt'];
				$markdown = $converter->parseString($post['message_fmt']);
				Topic::query()->create([
					'title' => $title,
					'content' => $content,
					'markdown' => $markdown,
					'view' => $view,
					'user_id' => $user_id,
					'status' => 'publish',
					'tag_id' => $tag_id,
					'_token' => Str::random(),
					"like" =>0,
					'options' => '{"summary":"\n","images":[]}',
					'updated_user' => $user_id
				]);
			}
		}
	}
	
	private function migrate_comment(MySQL $mysql, mixed $tablepre)
	{
		$sql = "SELECT * FROM {$tablepre}post WHERE isfirst=0";
		foreach ($mysql->query($sql) as $post){
			$sql_thread = "SELECT * FROM {$tablepre}thread WHERE tid='{$post['tid']}'";
			$title = $mysql->query($sql_thread)[0]['subject'];
			$topic_id = Topic::query()->where('title',$title)->value('id');
			$sqlUser = "SELECT * FROM {$tablepre}user WHERE uid='{$post['uid']}'";
			$user = $mysql->query($sqlUser)[0];
			$user_id = 0;
			if(User::query()->where(['email'=>$user['email']])->orWhere(['username'=>$user['username']])->exists()){
				$user_id = User::query()->where(['email'=>$user['email']])->value('id');
			}
			$content = $post['message_fmt'];
			$converter = new \Markdownify\Converter;
			$markdown = $converter->parseString($post['message_fmt']);
			if(!TopicComment::query()->where(['topic_id'=>$topic_id,'user_id'=>$user_id,'content'=>$content])->exists()){
				TopicComment::query()->create([
					'user_id'=>$user_id,
					'topic_id'=>$topic_id,
					'like' => 0,
					'content' => $content,
					'markdown' => $markdown,
					'status' => 'publish',
				]);
			}
			
		}
	}
}