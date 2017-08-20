<?php
    /* This file is part of Jeedom.
     *
     * Jeedom is free software: you can redistribute it and/or modify
     * it under the terms of the GNU General Public License as published by
     * the Free Software Foundation, either version 3 of the License, or
     * (at your option) any later version.
     *
     * Jeedom is distributed in the hope that it will be useful,
     * but WITHOUT ANY WARRANTY; without even the implied warranty of
     * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
     * GNU General Public License for more details.
     *
     * You should have received a copy of the GNU General Public License
     * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
     */
    
    require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');
    if (!isConnect()) {
        include_file('desktop', '404', 'php');
        die();
    }
    ?>
<form class="form-horizontal">
<fieldset>
<div class="form-group">
<label class="col-lg-4 control-label">Mon Email Melcloud</label>
<div class="col-lg-2">
<input class="configKey form-control" data-l1key="MyEmail" />
</div>
</div>


<div class="form-group">
<label class="col-lg-4 control-label">Mon Mot de passe Melcloud</label>
<div class="col-lg-2">
<input class="configKey form-control" data-l1key="MyPassword" />
</div>

</br>
</br>
</br>
</br>
<div class="form-group">
<label class="col-lg-4 control-label">Etape 1 : </label>
<div class="col-lg-8">
<a class="btn btn-success bt_restartTeleinfoDeamon">Obtenir Token aupres de Melcloud</a>
</div>

</div>


<div class="form-group">
<label class="col-lg-4 control-label">Etape 2 : </label>
<div class="col-lg-8">
<a class="btn btn-success bt_restartTeleinfoDeamon2">Mise à Jour des informations</a>
</div>
</div>

<div class="form-group">
<label class="col-lg-4 control-label">Mon Token Melcloud (ne pas remplir)</label>
<div class="col-lg-2">
<input class="configKey form-control" data-l1key="MyToken" />
</div>
</div>

</div>
</fieldset>
</form>


<script>
$('.bt_restartTeleinfoDeamon').on('click', function () {
                                  $.ajax({// fonction permettant de faire de l'ajax
                                         type: "POST", // methode de transmission des données au fichier php
                                         url: "plugins/melcloud/core/ajax/melcloud.ajax.php", // url du fichier php
                                         data: {
                                         action: "gettoken",
                                         id : $(this).closest('.slaveConfig').attr('data-slave_id')
                                         },
                                         dataType: 'json',
                                         error: function (request, status, error) {
                                         handleAjaxError(request, status, error);
                                         },
                                         success: function (data) {
                                         $('#div_alert').showAlert({message: data.result, level: 'danger'});
                                         }
                                         
                                         });
                                  });

$('.bt_restartTeleinfoDeamon2').on('click', function () {
                                   $.ajax({// fonction permettant de faire de l'ajax
                                          type: "POST", // methode de transmission des données au fichier php
                                          url: "plugins/melcloud/core/ajax/melcloud.ajax.php", // url du fichier php
                                          data: {
                                          action: "pull",
                                          id : $(this).closest('.slaveConfig').attr('data-slave_id')
                                          },
                                          dataType: 'json',
                                          error: function (request, status, error) {
                                          handleAjaxError(request, status, error);
                                          },
                                          success: function (data) {  
                                          $('#div_alert').showAlert({message: data.result, level: 'danger'});
                                          }
                                          
                                          });
                                   });
</script>
