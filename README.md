mvc模式里的 c 也就是控制器，在每次编写代码的过程中，其中有一大部分高度复用的
如果把这部分代码解耦出来，并且保证其原子性，成为可以高度复用的方法，可以带来极大的好处
可以减少重复的代码，在减少代码的同时也会减少bug代码的产生，并且容易修改
这是Repository模式的作用

那控制器里的代码 有多少可以封装到respository类里呢？
我觉得是90%以上(除了调用代码)
所以我在这里想提出一种模式
也就是标题 方法容器-流程控制模式

简单来说就是
有一个方法容器，可以依次调用你定义的方法(也就是respository里的方法)，最后返回参数

1. 它的好处是，像laravel调用控制器的方法一样 自动传入参数，你只需要在方法中申明需要的参数
方法容器去调用的时候，会自动传入参数，这些参数就是方法容器类里的参数这个属性里的，或者是laravel容器里定义的对象(这个就和控制器里的一样)  变量是前者，而申明的是类就是后者   抄袭了laravel里的代码
2. 可以对单个方法做缓存
3. 强迫自己写原子性的方法，提高代码的复用
4. 让控制器里的代码非常直观

而在写控制器方法的时候，其实写的是调用流程，我们一起来看一下到底是咋回事

这是git仓库(看名字就知道 依赖于laravel)
[laravel-function-flow](https://github.com/anyuzhe/laravel-function-flow)

首先用composer加载包
>composer require anyuzhe/laravel-function-flow

在配置文件config/app.php的服务容器数组中加入
>\Anyuzhe\LaravelFunctionFlow\FunctionFlowServiceProvider::class,

在门面数组中加入
>'Flow'      => \Anyuzhe\LaravelFunctionFlow\FlowFacade::class,

执行命令 生成配置文件

>php artisan funcFlow:publish

会在config文件夹中生成function-flow.php文件
>可在里面配置仓库类 类似如下

````
return array(
//'Base'=>BaseController::class 类似这样的key value定义类 名称和位置 无任何限制
	'Default' => '',//这是默认方法 也就是不写类名时候 默认去找的类
	'GoodsRecord' => \App\Repositories\GoodsRepository::class,
	'Picture' => \App\Repositories\PicturesRepository::class,
	'Store'  => \App\Repositories\StoresRepository::class,
	'Goods'  => \App\Repositories\GoodsRepository::class,
	'GoodsRecord'  => \App\Repositories\GoodsRecordsRepository::class,
	'Response'  => \App\ZL\ResponseLayout::class,
	'Qrcode'   => \App\Repositories\QrcodesRepository::class,
	'User'   => \App\Repositories\UsersRepository::class,
);
````


以下是控制器中的使用示例
````
public function bindRecord(Request $request)
{
	return \Flow::setLastFunc(['Response/flowReturn'])->setParam($request->all())->flow([
			['Store/getIdByNo'],
			['User/bindRecord'],
			['User/checkMemberExist'],
	])['response'];
}
````

首先除了flow方法。别的方法调用都是返回对象本身 所以可以链式调用 以上是facade模式的调用
再看一下这个详细的例子

````
public function beforeStore($id, $data)
{
	$data['cover_url'] = 'http://weiyicrm.oss-cn-shanghai.aliyuncs.com/ico-fuzhaung.png';
	$data['input_goods_no'] = isset($data['goods_no'])?$data['goods_no']:null;
	$data['id'] = $id;
	//setParam方法是设置运行中的参数 可以是数组 或者是两个参数键值的形式
	return \Flow::setParam($data)->flow([   //flow为主运行方法
			['Store/getOneId'],
			//  数组第二个参数是array型 可以设置函数的额外参数  第三个参数int型 是一个缓存的时间值可以设置是否缓存 单位为分钟
			['Qrcode/generateGoodsNoAndCreateQrcodeByNum',['created_pic'=>false]],
			['Picture/getIds'],
			['Picture/getModelByIds'],
			['Goods/getGoodsNo'],
			['Goods/findByNo'],
			['Goods/updateByStock'],
			['Goods/updateBySell'],
			['Goods/createOne'],
			['Qrcode/bingQrcodeGoodsNo'],// 
			['Qrcode/bingQrcodeLabelNo'],// 
			['GoodsRecord/updateOne'],
			['Response/flowReturn'],
	])['response'];
}
````
此例子中并没有使用setLastFunc方法（用来设置最后运行方法的）
需要注意的是缓存是用了类名+方法名+参数转成字符串的值作为缓存的键名。
可以适用于一些场景 可以配合前面函数输出缓存的依据参数 配合使用应该还不错



最后看一下 几个方法的实例 了解下方法的编写
注意方法中的参数都是通过方法容器自动传入的(变量名与参数名相同) 如果方法容器的参数数组中不存在并且没有默认值 会传入null
flow方法会把方法容器的parameters成员返回 也就是所有的参数

````
这一用来返回最后的reponse数组的方法。 判断在别的方法中是否输出了错误码和错误信息
public function flowReturn($res,$errcode,$errmsg,$msg)
{
	if(!$errcode){
			$data['status'] = 1;
			$data['data'] = $res;
			$data['msg'] = $msg?$msg:'Success';
	}else{
			$data['status'] = $errcode;
			$data['data'] = null;
			$data['msg'] = $errmsg?$errmsg:'Error';
	}
	return ['response'=>$data];
}
````


````
public function getOneId()
{
	$user = Auth::user();
	if($user) {
			$stores = $user->stores;
			if ($stores->count() == 1) {
					//最后输出的数组 会合并入方法容器的参数数组中 之后的方法中 可以申明参数 只要变量名与参数名相同 就可以自动传入
					return ['store_id'=>$stores->first()->id];
			}
	}
	//这里返回的skip是可以跳过之后的一些方法  直接运行想要的方法 可以作为报错后的处理   这里出错 所以返回了错误码和错误信息
	return ['skip'=>'flowReturn','store_id'=>null,'errcode'=>ErrorCode::$canNotFindStoreError['code'],'errmsg'=>ErrorCode::$canNotFindStoreError['msg']];
}
````


````
public function getIdByNo($store_no)
{
	if($store_no){
			$store = $this->findBy('identifying',$store_no);
			if($store){
					return ['store_id'=>$store->id];
			}
	}
	//这里返回的next为false 会让方法容器不执行之后的方法。直接运行lastFunc（如果定义了的话）ps：可以把最后的返回参数处理函数定义为lastFunc
	return ['next'=>false,'store_id'=>null,'errcode'=>ErrorCode::$canNotFindStoreError['code'],'errmsg'=>ErrorCode::$canNotFindStoreError['msg']];
}
````

有兴趣的小伙伴可以体验一下，也可以来github上提bug和问题，我会立刻改正