<?php
namespace Modules\Stations\Controllers;

use Entity\Station;
use Entity\StationMount;

class MountsController extends BaseController
{
    protected function preDispatch()
    {
        $fa = $this->station->getFrontendAdapter($this->di);
        if (!$fa->supportsMounts())
            throw new \App\Exception(_('This station does not currently support mount points. Switch to a different frontend to enable mount point management.'));

        return parent::preDispatch();
    }

    protected function permissions()
    {
        return $this->acl->isAllowed('manage station mounts', $this->station->id);
    }

    public function indexAction()
    {
        $this->view->frontend_adapter = $this->station->getFrontendAdapter($this->di);
        $this->view->mounts = $this->station->mounts;
    }

    public function editAction()
    {
        $form_config = $this->current_module_config->forms->mount;
        $form = new \App\Form($form_config);

        if ($this->hasParam('id'))
        {
            $record = $this->em->getRepository(StationMount::class)->findOneBy(array(
                'id' => $this->getParam('id'),
                'station_id' => $this->station->id,
            ));
            $form->setDefaults($record->toArray($this->em));
        }

        if(!empty($_POST) && $form->isValid($_POST))
        {
            $data = $form->getValues();

            if (!($record instanceof StationMount))
            {
                $record = new StationMount;
                $record->station = $this->station;
            }

            $record->fromArray($this->em, $data);

            $this->em->persist($record);

            $uow = $this->em->getUnitOfWork();
            $uow->computeChangeSets();
            if ($uow->isEntityScheduled($record))
            {
                $this->station->needs_restart = true;
                $this->em->persist($this->station);
            }

            $this->em->flush();
            $this->em->refresh($this->station);

            $this->alert('<b>'._('Record updated.').'</b>', 'green');

            return $this->redirectFromHere(['action' => 'index', 'id' => NULL]);
        }

        $title = ($this->hasParam('id')) ? _('Edit Record') : _('Add Record');
        return $this->renderForm($form, 'edit', $title);
    }

    public function deleteAction()
    {
        $id = (int)$this->getParam('id');

        $record = $this->em->getRepository(StationMount::class)->findOneBy(array(
            'id' => $id,
            'station_id' => $this->station->id
        ));

        if ($record instanceof StationMount)
            $this->em->remove($record);

        $this->station->needs_restart = true;
        $this->em->persist($this->station);
        $this->em->flush();

        $this->em->refresh($this->station);

        $this->alert('<b>'._('Record deleted.').'</b>', 'green');
        return $this->redirectFromHere(['action' => 'index', 'id' => NULL]);
    }
}
