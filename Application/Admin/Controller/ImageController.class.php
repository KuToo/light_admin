<?php 
namespace Admin\Controller;
use Think\Page;
use Admin\Controller\PrivilegeController;
/**
* 商品图片类
*/
class ImageController extends PrivilegeController
{
    /**
     * [productImages 某件商品的图片]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-23
     * @return    [type]     [description]
     */
    public function productImages()
    {
        $product_id=I('get.product_id');
        $imgs=M('product_imgs')->where(['product_id'=>$product_id])->select();
        $count=count($imgs);
        $this->assign('imgs',$imgs);
        $this->assign('count',$count);
        $this->display();
    }
    /**
     * [setFace 设置商品封面]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-23
     */
    public function setFace()
    {
        $product_id=I('post.product_id',0);
        $img_id=I('post.img_id',0);
        if($product_id==0 || $img_id==0){
            $this->ajaxReturn(['code'=>'1','msg'=>'参数错误']);
        }else{
            $is_face=M('product_imgs')->where(['product_id'=>$product_id,'img_id'=>$img_id])->getField('is_face');
            if($is_face==1){
                $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
            }else{
                M()->startTrans();
                $res1=M('product_imgs')->where(['product_id'=>$product_id])->save(['is_face'=>2]);
                $res2=M('product_imgs')->where(['product_id'=>$product_id,'img_id'=>$img_id])->save(['is_face'=>1]);
                if($res1===false || $res2===false){
                    M()->rollback();
                    $this->ajaxReturn(['code'=>'1','msg'=>'设置失败']);
                }else{
                    M()->commit();
                    $this->ajaxReturn(['code'=>'0','msg'=>'设置成功']);
                }
            }
        }
    }
    /**
     * [modImgStatus 改变商品图片状态]
     * @AuthorHTL Mr.yang
     * @DateTime  2018-04-23
     * @return    [type]     [description]
     */
    public function modImgStatus()
    {
        $status=I('post.status');
        $id=I('post.id');
        $status1= $status == 1 ? 0 : 1 ;
        $res=M('product_imgs')->where(['id'=>$id])->save(['status'=>$status1]);
        $text= $status1 == 1 ? '成功启用' : '成功禁用' ;
        if($res===false){
            $this->ajaxReturn(['code'=>1,'msg'=>$text]);
        }else{
            $this->ajaxReturn(['code'=>0,'msg'=>$text]);
        }
    }
}