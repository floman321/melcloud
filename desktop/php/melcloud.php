<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}

$plugin = plugin::byId('melcloud');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

?>
    <div class="row row-overflow">
    <div class="col-lg-2 col-md-3 col-sm-4">
        <div class="bs-sidebar">
            <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
                <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;"
                   data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un matériel}}</a>
                <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm"
                                                                      placeholder="{{Rechercher}}" style="width: 100%"/>
                </li>
                <?php
                foreach ($eqLogics as $eqLogic) {
                    echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">

        <legend><i class="fa fa-cog fa-spin"></i> {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction" data-action="add"
                 style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
                <center>
                    <i class="fa fa-plus-circle" style="font-size : 5em;color:#94ca02;"></i>
                </center>
                <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#94ca02"><center>Ajouter</center></span>
            </div>
            <div class="cursor eqLogicAction" data-action="gotoPluginConf"
                 style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
                <center>
                    <i class="fa fa-wrench" style="font-size : 5em;color:#767676;"></i>
                </center>
                <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Configuration}}</center></span>
            </div>
        </div>

        <legend><i class="fa fa-list-alt"></i> {{Mes équipements MelCloud}}</legend>

        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
                echo "<center>";
                echo '<img src="plugins/melcloud/doc/images/melcloud_appareil_icon.png" height="105" width="95" />';
                echo "</center>";
                echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>

    <div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">

        <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i>  {{Sauvegarder}}</a>
        <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
        <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>


        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="return" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
            <li role="presentation" class="active"><a href="#materiel" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Matériel}}</a></li>
            <li role="presentation"><a href="#commandes" aria-controls="commandes" role="tab" data-toggle="tab"><iclass="fa fa-list-alt"></i> {{Commandes}}</a></li>
        </ul>

        <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="materiel">
                <br />
                <form class="form-horizontal">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Nom de matériel}}</label>
                            <div class="col-sm-3">
                                <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
                                <input type="text" class="eqLogicAttr form-control" data-l1key="name"
                                       placeholder="{{Nom de l'équipement}}"/>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Objet parent}}</label>
                            <div class="col-sm-3">
                                <select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
                                    <option value="">{{Aucun}}</option>
                                    <?php
                                    foreach (jeeObject::all() as $object) {
                                        echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-sm-3 control-label">{{Activer}}</label>
                            <div class="col-sm-9">
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable"
                                                                      checked/>{{Activer}}</label>
                                <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible"
                                                                      checked/>{{Visible}}</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">Nom Machine</label>
                            <div class="col-sm-3">
                                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration"
                                       data-l2key="namemachine" placeholder="Nom de la machine *"/>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">Device ID</label>
                            <div class="col-sm-3">
                                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration"
                                       data-l2key="deviceid" placeholder="Ne pas Remplir"/>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-sm-3 control-label">Build ID</label>
                            <div class="col-sm-3">
                                <input type="text" class="eqLogicAttr configuration form-control" data-l1key="configuration"
                                       data-l2key="buildid" placeholder="Ne pas Remplir"/>
                            </div>
                        </div>
                        
                        
                         <div class="form-group">
                            <label class="col-sm-3 control-label">Rubriques Exploitables : </label>
                            <div class="col-sm-3">                              
                                <textarea name="textarea" rows="10" cols="50" class="eqLogicAttr configuration " data-l1key="configuration"
                                       data-l2key="rubriques"> </textarea>
                            </div>
                        </div>


                    </fieldset>
                </form>
            </div>
            <div role="tabpanel" class="tab-pane" id="commandes">
                <a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i class="fa fa-plus-circle"></i> {{Ajouter une commande}}</a>
                <br/><br/>
                <table id="table_cmd" class="table table-bordered table-condensed">
                    <thead>
                    <tr>
                        <th>{{Nom Affiché}}</th>
                        <th>{{Rubrique Melcloud}}</th>
                        <th>{{Type}}</th>
                        <th>{{Option}}</th>
                        <th>{{Divers}}</th>
                        <th>{{Action}}</th>
                    </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
                <a class="btn btn-success eqLogicAction" data-action="save"><i class="fa fa-check-circle"></i>  {{Sauvegarder}}</a>
            </div>
        </div>
    </div>

<?php include_file('desktop', 'melcloud', 'js', 'melcloud'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
