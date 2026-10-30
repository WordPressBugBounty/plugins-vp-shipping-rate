jQuery(document).ready(function($) {

  //Settings page
  var vp_shipping_rate_settings = {
		$pricing_table: $('.vp-shipping-rate-settings-pricings'),
		init: function() {

			//Conditional logic controls
			var conditional_fields = [this.$pricing_table];
			var conditional_fields_ids = ['pricings'];

			//Setup conditional fields for pricing
			conditional_fields.forEach(function(table, index){
				var id = conditional_fields_ids[index];
				var singular = id.slice(0, -1);
				singular = singular.replace('_', '-');
				table.on('change', 'select.condition', {group: id}, vp_shipping_rate_settings.change_x_condition);
				table.on('change', 'select.vp-shipping-rate-settings-repeat-select', function(){vp_shipping_rate_settings.reindex_x_rows(id)});
				table.on('click', '.add-row', {group: id}, vp_shipping_rate_settings.add_new_x_condition_row);
				table.on('click', '.delete-row', {group: id}, vp_shipping_rate_settings.delete_x_condition_row);
				table.on('click', '.delete-'+singular, {group: id}, vp_shipping_rate_settings.delete_x_row);
				$('.vp-shipping-rate-settings-'+singular+'-add a.add:not([data-disabled]').on('click', {group: id, table: table}, vp_shipping_rate_settings.add_new_x_row);
				table.on('click', '.add-condition', {group: id}, vp_shipping_rate_settings.add_new_x_condition);

				//If we already have some notes, append the conditional logics
				table.find('ul.conditions[data-options]').each(function(){
					var saved_conditions = $(this).data('options');
					var ul = $(this);

					saved_conditions.forEach(function(condition){
						var sample_row = $('#vp_shipping_rate_'+id+'_condition_sample_row').html();
						sample_row = $(sample_row);
						sample_row.find('select.condition').val(condition.category);
						vp_shipping_rate_settings.filter_comparison_options(sample_row.find('select.condition'));
						sample_row.find('select.comparison').val(condition.comparison);
						sample_row.find('.value').removeClass('selected');
						sample_row.find('.value[data-condition="'+condition.category+'"]').val(condition.value).addClass('selected').attr('disabled', false);
						ul.append(sample_row);
					});
				});

				//Reindex the fields
				vp_shipping_rate_settings.reindex_x_rows(id);

			});

			//Weight based pricing toggle
			this.$pricing_table.on('click', '.add-weight-row', vp_shipping_rate_settings.add_new_pricing_weight_row);
			this.$pricing_table.on('click', '.delete-weight-row', vp_shipping_rate_settings.delete_pricing_weight_row);
			this.$pricing_table.on('change', 'input.weight-based', vp_shipping_rate_settings.toggle_pricing_weight);
				
			//If we already have some weight pricing, append to the table
			this.$pricing_table.find('.vp-shipping-rate-settings-pricing-weight-options').each(function(){
				var saved_options = $(this).data('options');
				var tbody = $(this);
				if(saved_options) {
					saved_options.forEach(function(condition){
						var sample_row = $('#vp_shipping_rate_weight_range_sample_row').html();
						sample_row = $(sample_row);
						sample_row.find('input.min').val(condition.min);
						sample_row.find('input.max').val(condition.max);
						sample_row.find('input.cost').val(condition.cost);
						tbody.append(sample_row);
					});
				} else {
					var sample_row = $('#vp_shipping_rate_weight_range_sample_row').html();
					sample_row = $(sample_row);
					tbody.append(sample_row);
				}

				vp_shipping_rate_settings.reindex_x_rows('pricings');
			});

			//Copy function for pricing row
			this.$pricing_table.on('click', '.duplicate-pricing', function(e){
				e.preventDefault();
				var $row = $(this).closest('.vp-shipping-rate-settings-pricing');

				$row.find('select option').each(function(){
					if(this.selected) $(this).attr('selected', 'selected');
					else $(this).removeAttr('selected');
				});

				var $newRow = $row.clone();
				$row.after($newRow);
				vp_shipping_rate_settings.reindex_x_rows('pricings');
				return false;
			});

			//Move up & down function for pricing row
			this.$pricing_table.on('click', '.move-up, .move-down', function(e){
				e.preventDefault();
				var $row = $(this).closest('.vp-shipping-rate-settings-pricing');
				if($(this).hasClass('move-up'))
					$row.prev().before($row);
				else
					$row.next().after($row);
				vp_shipping_rate_settings.reindex_x_rows('pricings');
				return false;
			});

			//Comment field: click edit icon (or the displayed comment text) to reveal the input
			this.$pricing_table.on('click', '.edit-comment, .vp-shipping-rate-settings-pricing-comment .comment-display', function(e){
				e.preventDefault();
				var $row = $(this).closest('.vp-shipping-rate-settings-pricing').find('.vp-shipping-rate-settings-pricing-comment');
				$row.addClass('editing');
				$row.find('input.comment-field').trigger('focus');
			});

			//Hide the input on blur and copy the value back to the displayed text
			this.$pricing_table.on('blur', '.vp-shipping-rate-settings-pricing-comment input.comment-field', function(){
				var $row = $(this).closest('.vp-shipping-rate-settings-pricing-comment');
				$row.find('.comment-display').text($(this).val());
				$row.removeClass('editing');
			});

			//Pressing Enter closes the editor without submitting the surrounding form
			this.$pricing_table.on('keydown', '.vp-shipping-rate-settings-pricing-comment input.comment-field', function(e){
				if(e.which === 13) {
					e.preventDefault();
					$(this).trigger('blur');
				}
			});

		},
		change_x_condition: function(event) {
			var condition = $(this).val();

			//Hide all selects and make them disabled(so it won't be in $_POST)
			$(this).parent().find('.value').removeClass('selected').prop('disabled', true);
			$(this).parent().find('.value[data-condition="'+condition+'"]').addClass('selected').prop('disabled', false);

			//Restrict the comparison dropdown to operators that make sense for this condition type
			vp_shipping_rate_settings.filter_comparison_options($(this));
		},
		filter_comparison_options: function($conditionSelect) {
			var $row  = $conditionSelect.parent();
			var $cmp  = $row.find('select.comparison');
			if(!$cmp.length) return;

			var type = $conditionSelect.find('option:selected').data('comparison-type') || 'numeric';

			//Cache the full set of option elements once so we can rebuild the select when the condition changes
			var $allOptions = $cmp.data('all-options');
			if(!$allOptions) {
				$allOptions = $cmp.find('option').clone();
				$cmp.data('all-options', $allOptions);
			}

			var previous = $cmp.val();
			$cmp.empty();
			$allOptions.each(function(){
				var applies = $(this).data('applies');
				if(applies === 'all' || applies === type) {
					$cmp.append($(this).clone());
				}
			});

			//Keep the previous selection if it's still available, otherwise fall back to 'equal'
			if(previous && $cmp.find('option[value="'+previous+'"]').length) {
				$cmp.val(previous);
			} else {
				$cmp.val('equal');
			}
		},
		add_new_x_condition_row: function(event) {
			var sample_row = $('#vp_shipping_rate_'+event.data.group+'_condition_sample_row').html();
			$(this).closest('ul.conditions').append(sample_row);
			vp_shipping_rate_settings.reindex_x_rows(event.data.group);
			return false;
		},
		add_new_x_condition: function(event) {
			var sample_row = $('#vp_shipping_rate_'+event.data.group+'_condition_sample_row').html();
			$(this).parent().find('ul.conditions').append(sample_row);
			vp_shipping_rate_settings.reindex_x_rows(event.data.group);
			$(this).parent().find('ul.conditions').find('select.condition').trigger('change');
			return false;
		},
		delete_x_condition_row: function(event) {
			$(this).parent().remove();
			vp_shipping_rate_settings.reindex_x_rows(event.data.group);
			return false;
		},
		reindex_x_rows: function(group) {
			var group = group.replace('_', '-');
			$('.vp-shipping-rate-settings-'+group).find('.vp-shipping-rate-settings-repeat-item').each(function(index){
				$(this).find('textarea, select, input').each(function(){
					var name = $(this).data('name');
					if(name) {
						name = name.replace('X', index);
						$(this).attr('name', name);
					}
				});

				//Reindex conditions too
				$(this).find('li').each(function(index_child){
					$(this).find('select, input').each(function(){
						var name = $(this).data('name');
						if(name) {
							name = name.replace('Y', index_child);
							name = name.replace('X', index);
							$(this).attr('name', name);
						}
					});
				});

				//Reindex weight pricing
				if(group == 'pricings') {
					$(this).find('tr').each(function(index_child){
						$(this).find('input').each(function(){
							var name = $(this).data('name');
							if(name) {
								name = name.replace('Y', index_child);
								name = name.replace('X', index);
								$(this).attr('name', name);
							}
						});
					});
				}

				$(this).find('.vp-shipping-rate-settings-repeat-select').each(function(){
					var val = $(this).val();
					if($(this).hasClass('vp-shipping-rate-settings-advanced-option-property')) {
						$('.vp-shipping-rate-settings-advanced-option-value option').hide();
						$('.vp-shipping-rate-settings-advanced-option-value option[value^="'+val+'"]').show();

						if(!$('.vp-shipping-rate-settings-advanced-option-value').val().includes(val)) {
							$('.vp-shipping-rate-settings-advanced-option-value option[value^="'+val+'"]').first().prop('selected', true);
						}
					}

					var label = $(this).find('option:selected').text();
					$(this).parent().find('label span').text(label);
					$(this).parent().find('label span').text(label);
					$(this).parent().find('label i').removeClass().addClass(val);
				});

			});

			$( document.body ).trigger( 'wc-enhanced-select-init' );

			return false;
		},
		add_new_x_row: function(event) {
			var group = event.data.group;
			var table = event.data.table;
			var singular = group.slice(0, -1);
			var sample_row = $('#vp_shipping_rate_'+singular+'_sample_row').html();
			sample_row = $(sample_row);
			table.append(sample_row);
			vp_shipping_rate_settings.reindex_x_rows(group);
			$( document.body ).trigger( 'wc-enhanced-select-init' );
			return false;
		},
		delete_x_row: function(event) {
			$(this).closest('.vp-shipping-rate-settings-repeat-item').remove();
			vp_shipping_rate_settings.reindex_x_rows(event.data.group);
			return false;
		},
		add_new_pricing_weight_row: function(event) {
			var sample_row = $('#vp_shipping_rate_weight_range_sample_row').html();
			$(this).closest('tbody').append(sample_row);
			vp_shipping_rate_settings.reindex_x_rows('pricings');
			return false;
		},
		delete_pricing_weight_row: function(event) {
			$(this).closest('tr').remove();
			vp_shipping_rate_settings.reindex_x_rows('pricings');
			return false;
		},
		toggle_pricing_weight: function(event) {
			var checked = $(this).is(":checked");
			var $weight_pricing = $(this).closest('.vp-shipping-rate-settings-pricing').find('.vp-shipping-rate-settings-pricing-weight');
			var $weight_pricing_options = $weight_pricing.find('.vp-shipping-rate-settings-pricing-weight-options');

			console.log(checked, $weight_pricing);

			if(checked) {
				//Add empty row if no condtions exists
				if($weight_pricing_options.find('tr').length < 1) {
					var sample_row = $('#vp_shipping_rate_weight_range_sample_row').html();
					$weight_pricing_options.append(sample_row);
				}
				$weight_pricing.show();
			} else {
				$weight_pricing.hide();
			}

			vp_shipping_rate_settings.reindex_x_rows('pricings');
		},
  	}

	//Init settings page
	if($('.vp-shipping-rate-settings-pricings').length) {
    	vp_shipping_rate_settings.init();
	}

});
