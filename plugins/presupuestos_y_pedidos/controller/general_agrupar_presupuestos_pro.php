<?php
/*
 * This file is part of FacturaSctipts
 * Copyright (C) 2014  Carlos Garcia Gomez  neorazorx@gmail.com
 * Copyright (C) 2014  Francesc Pineda Segarra  shawe.ewahs@gmail.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

require_model('presupuesto_proveedor.php');
require_model('ejercicio.php');
require_model('pedido_proveedor.php');
require_model('proveedor.php');
require_model('regularizacion_iva.php');
require_model('serie.php');

class general_agrupar_presupuestos_pro extends fs_controller
{
   public $presupuesto;
   public $desde;
   public $hasta;
   public $proveedor;
   public $resultados;
   public $serie;
   public $neto;
   public $total;
   
   public function __construct()
   {
      parent::__construct('general_agrupar_presupuestos_pro', 'Agrupar presupuestos', 'general', FALSE, FALSE);
   }
   
   protected function process()
   {
      $this->ppage = $this->page->get('general_presupuestos_prov');
      $this->presupuesto = new presupuesto_proveedor();
      $this->proveedor = new proveedor();
      $this->serie = new serie();
      $this->neto = 0;
      $this->total = 0;
      
      if( isset($_POST['desde']) )
         $this->desde = $_POST['desde'];
      else
         $this->desde = Date('d-m-Y');
      
      if( isset($_POST['hasta']) )
         $this->hasta = $_POST['hasta'];
      else
         $this->hasta = Date('d-m-Y');
      
      if( isset($_POST['idpresupuesto']) )
         $this->agrupar();
      else if( isset($_POST['proveedor']) )
      {
         $this->save_codproveedor($_POST['proveedor']);
         
         $this->resultados = $this->presupuesto->search_from_proveedor($_POST['proveedor'],
                 $_POST['desde'], $_POST['hasta'], $_POST['serie']);
         
         if($this->resultados)
         {
            foreach($this->resultados as $presu)
            {
               $this->neto += $presu->neto;
               $this->total += $presu->total;
            }
         }
         else
            $this->new_message("Sin resultados.");
      }
   }
   
   private function agrupar()
   {
      $continuar = TRUE;
      $presupuestos = array();
      
      if( $this->duplicated_petition($_POST['petition_id']) )
      {
         $this->new_error_msg('Petición duplicada. Has hecho doble clic sobre el botón guadar
               y se han enviado dos peticiones. Mira en <a href="'.$this->ppage->url().'">presupuestos</a>
               para ver si los presupuestos se han guardado correctamente.');
         $continuar = FALSE;
      }
      else
      {
         foreach($_POST['idpresupuesto'] as $id)
            $presupuestos[] = $this->presupuesto->get($id);
         
         $codejercicio = NULL;
         foreach($presupuestos as $presu)
         {
            if( !isset($codejercicio) )
               $codejercicio = $presu->codejercicio;
            
            if( !$presu->ptepedido )
            {
               $this->new_error_msg("El presupuesto <a href='".$presu->url()."'>".$presu->codigo."</a> ya está aprobado.");
               $continuar = FALSE;
               break;
            }
            else if($presu->codejercicio != $codejercicio)
            {
               $this->new_error_msg("Los ejercicios de los presupuestos no coinciden.");
               $continuar = FALSE;
               break;
            }
         }
         
         if( isset($codejercicio) )
         {
            $ejercicio = new ejercicio();
            $eje0 = $ejercicio->get($codejercicio);
            if($eje0)
            {
               if( !$eje0->abierto() )
               {
                  $this->new_error_msg("El ejercicio está cerrado.");
                  $continuar = FALSE;
               }
            }
         }
      }
      
      if($continuar)
      {
         if( isset($_POST['individuales']) )
         {
            foreach($presupuestos as $presu)
               $this->generar_pedido( array($presu) );
         }
         else
            $this->generar_pedido($presupuestos);
      }
   }
   
   private function generar_pedido($presupuestos)
   {
      $continuar = TRUE;
      
      $pedido = new pedido_proveedor();
      $pedido->automatica = TRUE;
      $pedido->editable = FALSE;
      $pedido->codalmacen = $presupuestos[0]->codalmacen;
      $pedido->coddivisa = $presupuestos[0]->coddivisa;
      $pedido->tasaconv = $presupuestos[0]->tasaconv;
      $pedido->codejercicio = $presupuestos[0]->codejercicio;
      $pedido->codpago = $presupuestos[0]->codpago;
      $pedido->codserie = $presupuestos[0]->codserie;
      $pedido->irpf = $presupuestos[0]->irpf;
      $pedido->numproveedor = $presupuestos[0]->numproveedor;
      $pedido->observaciones = $presupuestos[0]->observaciones;
      $pedido->recfinanciero = $presupuestos[0]->recfinanciero;
      $pedido->totalirpf = $presupuestos[0]->totalirpf;
      $pedido->totalrecargo = $presupuestos[0]->totalrecargo;
      
      /// obtenemos los datos actualizados del proveedor
      $proveedor = $this->proveedor->get($presupuestos[0]->codproveedor);
      if($proveedor)
      {
         $pedido->cifnif = $proveedor->cifnif;
         $pedido->codproveedor = $proveedor->codproveedor;
         $pedido->nombre = $proveedor->nombrecomercial;
      }
      
      /// calculamos neto e iva
      foreach($presupuestos as $presu)
      {
         foreach($presu->get_lineas() as $l)
         {
            $pedido->neto += ($l->cantidad * $l->pvpunitario * (100 - $l->dtopor)/100);
            $pedido->totaliva += ($l->cantidad * $l->pvpunitario * (100 - $l->dtopor)/100 * $l->iva/100);
         }
      }
      /// redondeamos
      $pedido->neto = round($pedido->neto, 2);
      $pedido->totaliva = round($pedido->totaliva, 2);
      $pedido->total = $pedido->neto + $pedido->totaliva;
      
      /// asignamos la mejor fecha posible, pero dentro del ejercicio
      $ejercicio = new ejercicio();
      $eje0 = $ejercicio->get($pedido->codejercicio);
      $pedido->fecha = $eje0->get_best_fecha($pedido->fecha);
      
      /*
       * comprobamos que la fecha de el pedido no esté dentro de un periodo de
       * IVA regularizado.
       */
      $regularizacion = new regularizacion_iva();
      
      if( $regularizacion->get_fecha_inside($pedido->fecha) )
      {
         $this->new_error_msg('El IVA de ese periodo ya ha sido regularizado.
            No se pueden añadir más pedidos en esa fecha.');
      }
      else if( $pedido->save() )
      {
         foreach($presupuestos as $presu)
         {
            foreach($presu->get_lineas() as $l)
            {
               $n = new linea_pedido_proveedor();
               $n->idpresupuesto = $presu->idpresupuesto;
               $n->idpedido = $pedido->idpedido;
               $n->cantidad = $l->cantidad;
               $n->codimpuesto = $l->codimpuesto;
               $n->descripcion = $l->descripcion;
               $n->dtolineal = $l->dtolineal;
               $n->dtopor = $l->dtopor;
               $n->irpf = $l->irpf;
               $n->iva = $l->iva;
               $n->pvpsindto = $l->pvpsindto;
               $n->pvptotal = $l->pvptotal;
               $n->pvpunitario = $l->pvpunitario;
               $n->recargo = $l->recargo;
               $n->referencia = $l->referencia;
               
               if( !$n->save() )
               {
                  $continuar = FALSE;
                  $this->new_error_msg("¡Imposible guardar la línea del artículo ".$n->referencia."! ");
                  break;
               }
            }
         }
         
         if($continuar)
         {
            foreach($presupuestos as $presu)
            {
               $presu->idpedido = $pedido->idpedido;
               $presu->ptepedido = FALSE;
               
               if( !$presu->save() )
               {
                  $this->new_error_msg("¡Imposible vincular el presupuesto con el nuevo pedido!");
                  $continuar = FALSE;
                  break;
               }
            }
         }
         else
         {
            if( $pedido->delete() )
               $this->new_error_msg("El pedido se ha borrado.");
            else
               $this->new_error_msg("¡Imposible borrar el pedido!");
         }
      }
      else
         $this->new_error_msg("¡Imposible guardar el pedido!");
   }
}