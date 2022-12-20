<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('mitsubishimelcloud');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<!-- Page d'accueil du plugin -->
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
		<!-- Boutons de gestion du plugin -->
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
			<div class="cursor eqLogicAction bt_Synch" data-action="bt_Synch">
				<i class="fas fa-sync"></i>
				<br/>
				<span>{{Synchroniser}}</span>
			</div>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes équipements}}</legend>
		<?php
		if (count($eqLogics) == 0) {
			echo '<br/><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement Template n\'est paramétré, cliquer sur "Ajouter" pour commencer}}</div>';
		} else {
			// Champ de recherche
			echo '<div class="input-group" style="margin:5px;">';
			echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic"/>';
			echo '<div class="input-group-btn">';
			echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
			echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
			echo '</div>';
			echo '</div>';
			// Liste des équipements du plugin
			echo '<div class="eqLogicThumbnailContainer">';
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
				echo '<img src="plugins/' . $plugin->getId() . '/plugin_info/equipment_icon.png"/>';
				echo '<br>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		?>
	</div> <!-- /.eqLogicThumbnailDisplay -->

	<!-- Page de présentation de l'équipement -->
	<div class="col-xs-12 eqLogic" style="display: none;">
		<!-- barre de gestion de l'équipement -->
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs">  {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<!-- Onglets -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<!-- Onglet de configuration de l'équipement -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<!-- Partie gauche de l'onglet "Equipements" -->
				<!-- Paramètres généraux de l'équipement -->
				<form class="form-horizontal">
					<fieldset>
						<div class="col-lg-9">
							<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label" >{{Objet parent}}</label>
								<div class="col-sm-7">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Catégorie}}</label>
								<div class="col-sm-7">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Options}}</label>
								<div class="col-sm-7">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
								</div>
							</div>
							<legend><i class="fas fa-cogs"></i> {{Gestion des scénarii}}</legend>
							<?php for($i = 1; $i <=4; $i++) {
							echo '<div class="form-group">
								<label class="col-sm-3 control-label" for="scenario'. $i. '">{{Scenario :}} '. $i. '</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="Scenario_'. $i. '" id="scenario'. $i. '" onchange="ShowScenario(' . $i . ')"/><br />
									<div id="ScenarioParameter_'.$i.'" style="display: none;">
									<div class="form-group">
										<label class="col-sm-3 control-label" for="mode'. $i. '">{{Mode :}}</label>
										<div class="col-sm-7">
											<select class="eqLogicAttr" data-l1key="configuration" data-l2key="Mode_'. $i. '" id="mode'. $i. '">
												<option value="8">{{Auto}}</option>
												<option value="3">{{Mode froid}}</option>
												<option value="2">{{Séchage}}</option>
												<option value="7">{{Ventilation}}</option>
												<option value="1">{{Mode chaud}}</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-3 control-label" for="Span'. $i. '">{{Fan speed :}}</label>
										<div class="col-sm-7">
											<select class="eqLogicAttr" data-l1key="configuration" data-l2key="FanSpeed_'. $i. '" id="Span'. $i. '">
												<option value="0">{{Auto}}</option>
												<option value="1">1</option>
												<option value="2">2</option>
												<option value="3">3</option>
												<option value="4">4</option>
												<option value="5">5</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-3 control-label" for="Hori'. $i. '">{{Horizontal vane :}}</label>
										<div class="col-sm-7">
											<select class="eqLogicAttr" data-l1key="configuration" data-l2key="HoriVane_'. $i. '" id="Hori'. $i. '">
												<option value="0">{{Auto}}</option>
												<option value="12">{{Basculer}}</option>
												<option value="1">{{Gauche}}</option>
												<option value="2">{{Milieu-gauche}}</option>
												<option value="3">{{Milieu}}</option>
												<option value="4">{{Milieu-droite}}</option>
												<option value="5">{{Droite}}</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-3 control-label" for="Verti'. $i. '">{{Vertical vane :}}</label>
										<div class="col-sm-7">
											<select class="eqLogicAttr" data-l1key="configuration" data-l2key="VertiVane_'. $i. '" id="Verti'. $i. '">
												<option value="0">{{Auto}}</option>
												<option value="7">{{Basculer}}</option>
												<option value="5">{{Bas}}</option>
												<option value="4">{{Milieu-bas}}</option>
												<option value="3">{{Milieu}}</option>
												<option value="2">{{Milieu-haut}}</option>
												<option value="1">{{haut}}</option>
											</select>
										</div>
									</div>
									<div class="form-group">
										<label class="col-sm-3 control-label" for="Temp'. $i. '">{{Temperature :}}
										<sup><i class="fas fa-question-circle tooltips" title="{{Depending of selected mode, minimum temperature can be up to 16°C}}"></i></sup>
										</label>
										<div class="col-sm-7">
											<select class="eqLogicAttr" data-l1key="configuration" data-l2key="Temp_'. $i. '" id="Temp'. $i. '">
												';
												for($j = 20; $j <=62; $j++) {
													echo '<option value="'. $j .'">'. $j/2 .'</option>';
												}
												echo '</select>
										</div>
									</div>
									</div>
								</div>
							</div>';
							} ?>

<!--
							<legend><i class="fas fa-cogs"></i> {{Paramètres d'affichage}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Scénarios}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="Scenarios"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Mode}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="Mode"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Vitesse de ventilation}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="FanSpeed"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Volet de soufflage horizontale}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="VaneHoriical"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Ailettes verticales}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="VaneVertical"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Température}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="Temperature"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Météo}}</label>
								<div class="col-sm-7">
									<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="Weather"/>
								</div>
							</div>-->
						</div>
						
						<!-- Partie droite de l'onglet "Équipement" -->
						<!-- Affiche l'icône du plugin par défaut mais vous pouvez y afficher les informations de votre choix -->
						<div class="col-lg-3">
							<legend><i class="fas fa-info"></i> {{Informations}}</legend>
							<div class="form-group">
								<div class="text-center">
									<img name="icon_visu" src="<?= $plugin->getPathImgIcon(); ?>" style="max-width:160px;"/>
								</div>
							</div>
						</div>
					</fieldset>
				</form>
				<hr>
			</div><!-- /.tabpanel #eqlogictab-->

			<!-- Onglet des commandes de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
				<br/><br/>
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th>{{Id}}</th>
								<th>{{Nom}}</th>
								<th>{{Type}}</th>
								<th>{{Options}}</th>
								<th>{{Etat}}</th>
								<th>{{Action}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtab-->

		</div><!-- /.tab-content -->
	</div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', $plugin->getId(), 'js', $plugin->getId());?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>