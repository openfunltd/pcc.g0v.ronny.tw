<?php

class IndexController extends Pix_Controller
{
    public function indexAction()
    {
        $this->view->current_date = Entity::search(1)->max('date')->date;
    }

    public function caseAction()
    {
        $terms = explode('/', $this->getURI());
        if (count($terms) < 4) {
            return $this->Redirect('/');
        }
        $oid = $terms[3];
        if (!$this->view->unit = Unit::find($oid)) {
            return $this->redirect('/');
        }
        $this->view->job_number = $job_number = $terms[4];
        if (count($terms) >= 6) {
            $date = $terms[5];
            $filename = $terms[6];
            if (!$this->view->entity = Entity::find(array($date, $filename))) {
                return $this->alert('找不到這標案', '/');
            }
        }

        $this->view->entities = Entity::search(array('oid' => $oid, 'job_number' => $job_number))->order('date ASC');
    }

    public function unitAction()
    {
        $terms = explode('/', $this->getURI());
        if (!$unit = Unit::find(strval($terms[3]))) {
            return $this->redirect('/');
        }
        $this->view->unit = $unit;
    }
}
