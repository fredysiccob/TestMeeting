<?php declare(strict_types=1);

namespace Siccob\Test\backend\codeRead;

$_SERVER['SERVER_NAME'] = 'siccob.solutions';

class User
{
    public static function getData(): array
    {
        return ['PermisosString' => ['PPDFP'] , 'Id' => 3];
    }
}

class Model
{

    private $transaction;
    private $operation;

    public function __construct()
    {
        $this->operation = false;
    }

    public function initTransaction(): void
    {
        $this->transaction = true;
    }

    public function update(string $string , array $columns , array $where): void
    {
        if (empty($columns) || empty($where)) {
            $this->operation = false;
            return;
        }

        $this->operation = true;
    }

    public function query(string $string): array
    {

        if (strrpos($string , 't_solicitudes_internas')) {
            return ['Folio' => '123'];
        }

        if (strrpos($string , 't_solicitudes')) {
            return ['pendings' => 10];
        }

        if (strrpos($string , 't_servicios_ticket')) {
            return ['ID' => '123' , 'pendings' => 0];
        }

        return [];
    }

    public function commit()
    {

    }

    public function roolback()
    {

    }

    public function updateInventoriesMovementsByConclusionCorrective($servicio): bool
    {
        return true;
    }
}

class Email
{

    private $send;

    public function setMessage(string $title , string $emailContent): string
    {
        return "Subject: $title Message: $emailContent";
    }

    public function send(array $config , string $title , $message): void
    {
        if (empty($config)) {
            throw new \Exception('error send email');
        }

        $this->send = true;
    }
}

class Service
{
    private $lapData;
    private $endRequest;
    private $endTicket;
    private $status;

    public function check(array $data): array
    {
        date_default_timezone_set('America/Mexico_City');
        $user = User::getData();
        $db = new Model();
        $email = new Email();

        try {

            if (in_array("PPDFP" , $user["PermisosString"])) {
                $permissionPDF = true;
            } else {
                $permissionPDF = false;
            }

            $db->initTransaction();
            $host = $_SERVER['SERVER_NAME'];
            $date = date('%Y-%m-%d %H:%i:%s');

            $db->update('t_servicios_ticket' , array(
                'IdUsuarioValida' => $user['Id'] ,
                'FechaValidacion' => $date
            ) , array('Id' => $data['servicio']));

            $this->changeStatus($data['servicio'] , $date , '4');

            $inventory = $db->updateInventoriesMovementsByConclusionCorrective($data['servicio']);

            $ticket = $db->query('SELECT Id FROM t_servicios_ticket WHERE Ticket = "' . $data['ticket'] . '" AND IdEstatus in(10,5,2,1)');
            $counter = 0;
            $linkPDF = '';

            $conclusionData = $db->query('SELECT
                                            tst.Descripcion AS DescripcionServicio,
                                            tst.IdSolicitud,
                                            tsi.Asunto AS AsuntoSolicitud,
                                            tsi.Descripcion AS DescripcionSolicitud,
                                            (SELECT Folio FROM t_solicitudes WHERE Id = tst.IdSolicitud) Folio,
                                            tst.IdTipoServicio,
                                            tst.Firma,
                                            tst.FirmaTecnico,
                                            tst.IdValidaCinemex,
                                            tst.NombreFirma
                                           FROM t_servicios_ticket tst
                                           INNER JOIN t_solicitudes_internas tsi
                                           ON tsi.IdSolicitud = tst.IdSolicitud
                                           WHERE tst.Id = "' . $data['servicio'] . '"');

            if (empty($ticket)) {
                $this->endRequest($date , $data['idSolicitud']);
                $this->endTicket($data['ticket']);

                $completedServices = $db->query('SELECT 
                                                    tse.Id, 
                                                    tse.Ticket,
                                                    nombreUsuario(tso.Atiende) Atiende,
                                                    (SELECT EmailCorporativo FROM cat_v3_usuarios WHERE Id = tso.Atiende) CorreoAtiende,
                                                    tso.Solicita
                                                FROM t_servicios_ticket tse
                                                INNER JOIN t_solicitudes tso
                                                ON tse.IdSolicitud = tso.Id
                                                WHERE tse.Ticket = "' . $data['ticket'] . '"');

                foreach ($completedServices as $value) {
                    $counter++;
                    $linkPdfCompletedServices = $this->setPDF(array('servicio' => $data['servicio']));
                    $completedServicesData = $this->getInformation($value['Id']);
                    $servicesType = $this->stripAccents($completedServicesData[0]['NTipoServicio']);

                    if ($host === 'siccob.solutions' || $host === 'www.siccob.solutions') {
                        $path = 'https://siccob.solutions/storage/Archivos/Servicios/Servicio-' . $value['Id'] . '/Pdf/Ticket_' . $value['Ticket'] . '_Servicio_' . $value['Id'] . '_' . $servicesType . '.pdf';
                        $link = 'https://siccob.solutions/Detalles/Solicitud/' . $conclusionData[0]['IdSolicitud'];
                    } elseif ($host === 'pruebas.siccob.solutions' || $host === 'www.pruebas.siccob.solutions') {
                        $path = 'https://pruebas.siccob.solutions/storage/Archivos/Servicios/Servicio-' . $value['Id'] . '/Pdf/Ticket_' . $value['Ticket'] . '_Servicio_' . $value['Id'] . '_' . $servicesType . '.pdf';
                        $link = 'http://pruebas.siccob.solutions/Detalles/Solicitud/' . $conclusionData[0]['IdSolicitud'];
                    } else {
                        $path = 'https://' . $host . '/' . $linkPdfCompletedServices;
                        $link = 'https://' . $host . '/Detalles/Solicitud/' . $conclusionData[0]['IdSolicitud'];
                    }

                    if ($permissionPDF === true) {
                        $linkPDF .= '<br>Ver Servicio PDF-' . $counter . ' <a href="' . $path . '" target="_blank">Aquí</a>';
                    }

                }

                $title = 'Solicitud Concluida';
                $linkRequest = 'Ver detalles de la Solicitud <a href="' . $link . '" target="_blank">Aquí</a>';
                $emailContent = '<p>Estimado(a) <strong>' . $value['Atiende'] . ',</strong> se ha concluido la Solicitud.</p><br>Ticket: <strong>' . $value['Ticket'] . '</strong><br> Número Solicitud: <strong>' . $conclusionData[0]['IdSolicitud'] . '</strong><br><br>' . $linkRequest . '<br>' . $linkPDF;

                $message = $email->setMessage($title , $emailContent);
                $email->send([$value['CorreoAtiende']] , $title , $message);
            }

            $linkPDF = '/' . $host . '/Detalles/Solicitud/' . $conclusionData[0]['IdSolicitud'];;
            foreach ($conclusionData as $value) {
                if (isset($value['Folio'])) {
                    if ($value['Folio'] !== NULL) {
                        if ($value['Folio'] !== '0') {
                            $this->addLap(array(
                                'servicio' => $data['servicio'] ,
                                'folio' => $value['Folio']
                            ));

                            $this->verifyProcess($data);
                        }
                    }
                }
            }


            $pendingServices = $db->query("select * from t_servicios_ticket 
                                        tst inner join t_solicitudes ts on tst.IdSolicitud = ts.Id 
                                        where ts.Folio = {$conclusionData['Folio']} 
                                        AND tst.IdEstatus in(1,2,3,10,12,64)
                                        and tst.IdTipoServicio not in (21,70,5,64,69)
                                        and tst.Id <> {$data['servicio']}"
            );

            if (empty($pendingServices)) {
                $sdResponse = $this->setSDResolution($conclusionData['Folio'] , $data['servicio'] , $data['concluirSD']);
            } else {
                $sdResponse = $this->setSDResolution($conclusionData['Folio'] , $data['servicio'] , false);
            }

            if ($sdResponse['code'] == 500) {
                $result['message'] = $sdResponse['message'];
                $result['error'] = $sdResponse['error'];
            }

            $db->commit();
            $result = ['code' => 200 , 'folio' => $conclusionData['Folio'] , 'message' => 'correcto' , 'link' => $linkPDF , 'updateInventory' => $inventory];
        } catch (\Exception $ex) {
            $db->roolback();
            $result = array('code' => 400 , 'folio' => '' , 'message' => $ex->getMessage());
        }
        return $result;
    }

    private function endRequest($fecha , $idSolicitud): void
    {
        $this->endRequest = true;
    }

    private function endTicket($ticket): void
    {
        $this->endTicket = true;
    }

    private function changeStatus($servicio , string $date , string $string): void
    {
        $this->status = 'closed';
    }

    private function setPDF(array $array): string
    {
        return 'service.pdf';
    }

    private function getInformation($Id): array
    {
        return [['NTipoServicio' => 3]];
    }

    private function stripAccents($NTipoServicio): string
    {
        return 'reparacion';
    }

    private function addLap(array $data): void
    {
        $this->lapData = $data;
    }

    private function verifyProcess(array $data): void
    {
        if ($data['service'] !== '1') {
            throw new \Exception('No existe el servicio');
        }
    }

    private function setSDResolution($folio , $servicio , $concluirSD): array
    {
        if ($folio === '123') {
            return ['message' => 'ok' , 'code' => 200 , 'error' => ''];
        }

        return ['message' => 'Ups!! something went wrong' , 'code' => 500 , 'error' => 'Folio is not exist'];
    }
}
