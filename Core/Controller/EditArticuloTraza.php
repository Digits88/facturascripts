<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2013-2017  Carlos Garcia Gomez  <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Core\Controller;

use FacturaScripts\Core\Base\ExtendedController;

/**
 * Controlador para la edición de un registro del modelo ArticuloTraza
 *
 * @author PC REDNET S.L. <luismi@pcrednet.com>
 */
class EditArticuloTraza extends ExtendedController\EditController
{

    /**
     * Devuelve el nombre del modelo
     */
    public function getModelName()
    {
        return 'FacturaScripts\Core\Model\ArticuloTraza';
    }

    /**
     * Devuelve los datos básicos de la página
     *
     * @return array
     */
    public function getPageData()
    {
        $pagedata = parent::getPageData();
        $pagedata['title'] = 'traceability';
        $pagedata['menu'] = 'warehouse';
        $pagedata['icon'] = 'fa-barcode';
        $pagedata['showonmenu'] = false;

        return $pagedata;
    }
}