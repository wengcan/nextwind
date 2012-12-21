<?php
Wind::import('LIB:base.PwBaseController');
/**
 * the last known user to change this file in the repository  <$LastChangedBy: gao.wanggao $>
 * @author $Author: gao.wanggao $ Foxsee@aliyun.com
 * @copyright ?2003-2103 phpwind.com
 * @license http://www.phpwind.com
 * @version $Id: ImportController.php 22339 2012-12-21 09:37:22Z gao.wanggao $ 
 * @package 
 */
class ImportController extends PwBaseController {
	
	public  function beforeAction($handlerAdapter) {
		parent::beforeAction($handlerAdapter);
		Wekit::load('design.PwDesignPermissions');
		$permissions = $this->_getPermissionsService()->getPermissionsForUserGroup($this->loginUser->uid);
		if ($permissions < PwDesignPermissions::IS_DESIGN ) $this->showError("DESIGN:permissions.fail");
	}
	
	public function run() {
	
	}
	
	public function dorunAction() {
		$pageid = (int)$this->getInput('pageid', 'post');
		$pageDs = $this->_getPageDs();
		$pageInfo = $pageDs->getPage($pageid);
		if (!$pageInfo) $this->showError("operate.fail");
		
		Wind::import('SRV:design.bo.PwDesignPageBo');
		$pageBo = new PwDesignPageBo($pageid);
		if ($pageBo->getLock()) $this->showError('DESIGN:page.edit.other.user');
		
		$ext = strtolower(substr(strrchr($_FILES['file']['name'], '.'), 1));
		if (!$ext || !in_array($ext, array('txt', 'zip'))) $this->showMessage("DESIGN::upload.fail");
		
		if ($ext == 'zip'){//$this->showError("DESIGN:page.emport.fail");
			$this->clearPage($pageInfo);
			$this->doZip($pageInfo);
		} else {
			$this->doTxt($pageInfo);
		}
		$this->_getDesignService()->clearCompile();
		$this->showMessage("operate.success");
	}
	
	protected function doZip($pageInfo) {
		$portal = $this->_getPortalDs()->getPortal($pageInfo['page_unique']);
		Wind::import('SRV:design.srv.PwDesignImportZip');
		$srv = new PwDesignImportZip($pageInfo['page_id']);
		if (!$srv->checkDirectory()) $this->showError("DESIGN:directory.not.writeable");
		$file = $this->_uploadFile();
		if (!$file || $file['type'] != 'zip') $this->showMessage("DESIGN::upload.fail");
		$filename = Wind::getRealDir('PUBLIC:attachment.'.$file['path']).$file['filename'];
		$resource = $srv->checkZip($filename);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		/*
		Wind::import('SRV:design.dm.PwDesignPortalDm');
		$dm = new PwDesignPortalDm($portal['id']);
	    $dm->setTemplate($pageInfo['page_id'])//以pageid命名的文件夹
	    	->setPageName($portal['pagename'])
	    	->setTitle($portal['title']);
		$resource = $this->_getPortalDs()->updatePortal($dm);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		*/
		//更新数据
		Wind::import('SRV:design.srv.data.PwAutoData');
		foreach ($srv->newIds AS $id) {
			if (!$id) continue;
			$autoSrv = new PwAutoData($id);
			$autoSrv->addAutoData();
		}
		return true;
	}

	protected function doTxt($pageInfo) {
		$srv = Wekit::load('design.srv.PwDesignImportTxt');
		$file = $this->_uploadFile();
		if (!$file || $file['type'] != 'txt') $this->showMessage("DESIGN:upload.fail");
		$srv = new PwDesignImportTxt();
		$srv->setPageInfo($pageInfo);
		$filename = Wind::getRealDir('PUBLIC:attachment.'.$file['path']).$file['filename'];
		$resource = $srv->checkTxt($filename);
		if ($resource instanceof PwError) $this->showError($resource->getError());
		$resource = $srv->importTxt();
		if ($resource instanceof PwError) {
			$srv->rollback();
			$this->showError($resource->getError());
		}
		//更新数据
		Wind::import('SRV:design.srv.data.PwAutoData');
		foreach ($srv->newIds AS $id) {
			if (!$id) continue;
			$autoSrv = new PwAutoData($id);
			$autoSrv->addAutoData();
		}
		return true;
	}
	
	protected function clearPage($pageInfo) {
		$pageid = $pageInfo['page_id'];
		//doclear start
		//@see DesignController->doclearAction
		$ids = explode(',', $pageInfo['module_ids']);
		$names = explode(',', $pageInfo['struct_names']);
		$moduleDs = $this->_getModuleDs();
		$bakDs = $this->_getBakDs();
		$dataDs = $this->_getDataDs();
		$pushDs = $this->_getPushDs();
		$imageSrv = Wekit::load('design.srv.PwDesignImage');
		// module
		$moduleDs->deleteByPageId($pageid);
		
		// data && push
		foreach ($ids AS $id) {
			$dataDs->deleteByModuleId($id);
			$pushDs->deleteByModuleId($id);
			$imageSrv->clearFolder($id);
		}
		
		//structure
		$ds = $this->_getStructureDs();
		foreach ($names AS $name) {
			$ds->deleteStruct($name);
		}
		
		//segment
		$this->_getSegmentDs()->deleteSegmentByPageid($pageid);
		$bakDs->deleteByPageId($pageid);
		//doclear end
	}
	
	private function _uploadFile() {
 		Wind::import('SRV:upload.action.PwDesignImportUpload');
		Wind::import('SRV:upload.PwUpload');
		$bhv = new PwDesignImportUpload();
		$upload = new PwUpload($bhv);
		if (($result = $upload->check()) === true) $result = $upload->execute();
		if ($result !== true) $this->showError($result->getError());
		return $bhv->getAttachInfo();
 	}
 	
	protected function _getPermissionsService() {
		return Wekit::load('design.srv.PwDesignPermissionsService');
	}
	
	private function _getDesignService() {
		return Wekit::load('design.srv.PwDesignService');
	}
 	
	private function _getStructureDs() {
		return Wekit::load('design.PwDesignStructure');
	}
 	
	private function _getModuleDs() {
		return Wekit::load('design.PwDesignModule');
	}
	
	private function _getBakDs() {
		return Wekit::load('design.PwDesignBak');
	}
	
	private function _getDataDs() {
		return Wekit::load('design.PwDesignData');
	}
	
	private function _getPushDs() {
		return Wekit::load('design.PwDesignPush');
	}
	
	private function _getSegmentDs() {
		return Wekit::load('design.PwDesignSegment');
	}
	
	private function _getPageDs() {
		return Wekit::load('design.PwDesignPage');
	}
	
	private function _getPortalDs() {
		return Wekit::load('design.PwDesignPortal');
	}
}
?>