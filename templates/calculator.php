<?php
/**
 * Calculator template.
 *
 * Outputs only the calculator container (no html/head/body). All markup is
 * scoped inside the plugin container so the bundled, scoped stylesheet cannot
 * affect the rest of the page.
 *
 * There is deliberately NO h1 here. This template renders inside a WordPress
 * page that already has its own heading, and a second h1 would compete with it
 * in the document outline. The highest heading level used here is h2.
 *
 * Visible text is Brazilian Portuguese (pt-BR) by default and carries data-i18n
 * keys. The bundled JavaScript can switch the interface between Portugues (BR),
 * Portugues (PT) and English through the language selector; it never defaults
 * to English.
 *
 * Accessibility: every form control is associated with a real label element (or
 * a visually hidden one where a visible label is shared by two controls), so the
 * accessibility tree exposes a name for every control without relying on
 * JavaScript. Icons are decorative inline SVGs marked aria-hidden.
 *
 * @package HorasTrabalhadas
 */

namespace HorasTrabalhadas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><div id="htrb-app" class="htrb-app">
	<div class="container">

		<!-- header -->
		<header class="app-header">
			<div class="brand">
				<p class="body-sm" data-i18n="sub"><?php echo esc_html__( 'Intervalos · horas extras · pagamento · líquido · gorjetas · exportar', 'horas-trabalhadas' ); ?></p>
			</div>
			<div class="flex gap-2 no-print">
				<label class="htrb-sr-only" for="htrb-lang" data-i18n="f_language"><?php echo esc_html__( 'Idioma', 'horas-trabalhadas' ); ?></label>
				<select class="select lang-select" id="htrb-lang">
					<option value="pt-BR">Português (BR)</option>
					<option value="pt-PT">Português (PT)</option>
					<option value="en">English</option>
				</select>
				<label class="theme-toggle" id="themeToggleLabel">
					<?php echo Icons::get( 'moon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span data-i18n="theme"><?php echo esc_html__( 'Tema', 'horas-trabalhadas' ); ?></span>
					<input type="checkbox" id="themeToggle" />
				</label>
				<button type="button" class="btn btn-ghost btn-sm" id="htrb-top">
					<?php echo Icons::get( 'arrow-up', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span data-i18n="top"><?php echo esc_html__( 'Topo', 'horas-trabalhadas' ); ?></span>
				</button>
			</div>
		</header>

		<!-- settings -->
		<section class="card card-pad mb-3" aria-labelledby="htrb-settings-h">
			<h2 class="htrb-sr-only" id="htrb-settings-h" data-i18n="s_settings"><?php echo esc_html__( 'Configurações do cálculo', 'horas-trabalhadas' ); ?></h2>
			<div class="grid-3">
				<div class="input-group">
					<label for="preset"><?php echo Icons::get( 'calendar-range', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_preset">Período</span></label>
					<select class="select" id="preset" aria-label="<?php echo esc_attr__( 'Período', 'horas-trabalhadas' ); ?>">
						<option value="thisWeek" data-i18n="o_thisWeek">Esta semana</option>
						<option value="lastWeek" data-i18n="o_lastWeek">Semana passada</option>
						<option value="thisBiweek" data-i18n="o_thisBiweek">Estas 2 semanas</option>
						<option value="thisMonth" data-i18n="o_thisMonth">Este mês</option>
						<option value="custom" data-i18n="o_custom">Período personalizado</option>
					</select>
				</div>
				<div class="input-group">
					<label for="startDate"><?php echo Icons::get( 'calendar-days', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_start">Data inicial</span></label>
					<input class="input" type="date" id="startDate" aria-label="<?php echo esc_attr__( 'Data inicial', 'horas-trabalhadas' ); ?>"/>
				</div>
				<div class="input-group">
					<label for="endDate"><?php echo Icons::get( 'calendar-check', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_end">Data final</span></label>
					<input class="input" type="date" id="endDate" />
				</div>
				<div class="input-group">
					<label for="weekStart"><?php echo Icons::get( 'calendar-clock', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_weekStarts">Semana começa em</span></label>
					<select class="select" id="weekStart">
						<option value="1" data-i18n="o_mon">Segunda-feira</option>
						<option value="0" data-i18n="o_sun">Domingo</option>
						<option value="6" data-i18n="o_sat">Sábado</option>
					</select>
				</div>
				<div class="input-group">
					<label for="timeFormat"><?php echo Icons::get( 'clock', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_timeFormat">Formato de hora</span></label>
					<select class="select" id="timeFormat">
						<option value="24" data-i18n="o_24h">24 horas</option>
						<option value="12" data-i18n="o_12h">12 horas (am/pm)</option>
					</select>
				</div>
				<div class="input-group">
					<label for="rate"><?php echo Icons::get( 'wallet', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_currencyRate">Moeda e valor/hora</span></label>
					<div class="flex gap-2">
						<label class="htrb-sr-only" for="currency" data-i18n="f_currency"><?php echo esc_html__( 'Moeda', 'horas-trabalhadas' ); ?></label>
						<select class="select w-cur" id="currency">
							<option>R$</option><option>€</option><option>$</option><option>£</option><option>₹</option><option>¥</option><option>A$</option><option>C$</option>
						</select>
						<input class="input flex-1" type="number" id="rate" min="0" step="0.01" placeholder="0.00" />
					</div>
				</div>
				<div class="input-group">
					<label for="listName"><?php echo Icons::get( 'tag', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_label">Rótulo (opcional)</span></label>
					<input class="input" type="text" id="listName" data-i18n-ph="ph_label" placeholder="<?php echo esc_attr__( 'ex.: Ana — julho de 2026', 'horas-trabalhadas' ); ?>" />
				</div>
				<div class="input-group">
					<label for="otRule"><?php echo Icons::get( 'zap', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_otRule">Regra de horas extras</span></label>
					<select class="select" id="otRule">
						<option value="none" data-i18n="o_none">Nenhuma</option>
						<option value="weekly40" data-i18n="o_weekly40">Semanal &gt;40h</option>
						<option value="daily" data-i18n="o_daily">Limite diário</option>
						<option value="ca" data-i18n="o_ca">Califórnia (8h/40h/12h)</option>
						<option value="ak" data-i18n="o_ak">Alasca (8h/40h)</option>
						<option value="nv" data-i18n="o_nv">Nevada (8h/40h)</option>
					</select>
				</div>
				<div class="input-group">
					<label for="otDailyThresh"><?php echo Icons::get( 'trending-up', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_dailyOt">Hora extra diária após (h)</span></label>
					<input class="input" type="number" id="otDailyThresh" min="0" step="0.5" value="8" />
				</div>
				<div class="input-group">
					<label for="otMult"><?php echo Icons::get( 'x', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_otMult">Multiplicador de HE (×)</span></label>
					<input class="input" type="number" id="otMult" min="1" step="0.1" value="1.5" />
				</div>
				<div class="input-group">
					<label for="dtThresh"><?php echo Icons::get( 'hourglass', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_dtAfter">Dobro após (h)</span> <span class="hint" data-i18n="w_optional">opcional</span></label>
					<input class="input" type="number" id="dtThresh" min="0" step="0.5" data-i18n-ph="ph_dt" placeholder="<?php echo esc_attr__( 'ex.: 12', 'horas-trabalhadas' ); ?>" />
				</div>
				<div class="input-group">
					<label for="taxPct"><?php echo Icons::get( 'receipt', 'lbl-ico' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="f_tax">Impostos / descontos (%)</span></label>
					<input class="input" type="number" id="taxPct" min="0" max="100" step="0.1" placeholder="0" />
				</div>
			</div>
			<div class="flex-wrap mt-3 no-print">
				<button type="button" class="btn btn-outline btn-sm" id="bulkFill"><?php echo Icons::get( 'wand-sparkles', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="b_setDefault">Definir horas padrão</span></button>
				<button type="button" class="btn btn-ghost btn-sm" id="clearAll"><?php echo Icons::get( 'eraser', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="b_clearAll">Limpar tudo</span></button>
			</div>
		</section>

		<!-- KPI cards -->
		<section class="stat-grid" aria-labelledby="htrb-totals-h">
			<h2 class="htrb-sr-only" id="htrb-totals-h" data-i18n="s_totals"><?php echo esc_html__( 'Totais', 'horas-trabalhadas' ); ?></h2>
			<div class="stat-card"><div class="ico-wrap"><?php echo Icons::get( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="num" id="kTotal">0:00</div><div class="lbl" data-i18n="k_total">Total de horas</div></div>
			<div class="stat-card"><div class="ico-wrap"><?php echo Icons::get( 'check-circle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="num" id="kReg">0:00</div><div class="lbl" data-i18n="k_regular">Normais</div></div>
			<div class="stat-card"><div class="ico-wrap"><?php echo Icons::get( 'zap' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="num" id="kOt">0:00</div><div class="lbl" data-i18n="k_overtime">Horas extras</div></div>
			<div class="stat-card"><div class="ico-wrap"><?php echo Icons::get( 'wallet' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="num" id="kGross">—</div><div class="lbl" data-i18n="k_gross">Pagamento bruto</div></div>
			<div class="stat-card"><div class="ico-wrap"><?php echo Icons::get( 'landmark' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div><div class="num" id="kNet">—</div><div class="lbl" data-i18n="k_net">Líquido (após impostos)</div></div>
		</section>

		<!-- table -->
		<div class="table-wrap mb-3">
			<table>
				<caption class="htrb-sr-only" data-i18n="s_timesheet"><?php echo esc_html__( 'Folha de horas por dia', 'horas-trabalhadas' ); ?></caption>
				<thead>
					<tr>
						<th scope="col" data-i18n="th_day">Dia</th>
						<th scope="col" data-i18n="th_start">Início</th>
						<th scope="col" data-i18n="th_end">Fim</th>
						<th scope="col" data-i18n="th_break">Intervalo (min)</th>
						<th scope="col" data-i18n="th_tips">Gorjetas</th>
						<th scope="col" data-i18n="th_work">Tempo trabalhado</th>
					</tr>
				</thead>
				<tbody id="rows"></tbody>
				<tfoot>
					<tr>
						<td data-i18n="tf_weeklyTotal">Total semanal</td>
						<td colspan="3" class="body-sm" id="footSpan"></td>
						<td id="footTips">—</td>
						<td class="worktime" id="footHours">0:00</td>
					</tr>
				</tfoot>
			</table>
		</div>

		<!-- results + saved -->
		<div class="grid-2 mb-3">
			<section class="card card-pad" aria-labelledby="htrb-breakdown-h">
				<h2 class="heading-md mb-2" id="htrb-breakdown-h"><?php echo Icons::get( 'list-checks', 'icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="r_breakdown">Resumo dos resultados</span></h2>
				<div id="breakdown"></div>
			</section>
			<section class="card card-pad" aria-labelledby="htrb-save-h">
				<div class="flex justify-between mb-2">
					<h2 class="heading-md" id="htrb-save-h"><?php echo Icons::get( 'hard-drive-download', 'icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="r_saveExport">Salvar e exportar</span></h2>
					<span class="body-sm" data-i18n="r_staysBrowser">fica no seu navegador</span>
				</div>
				<div class="flex-wrap no-print mb-2">
					<button type="button" class="btn btn-primary btn-sm" id="saveBtn"><?php echo Icons::get( 'save', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="b_save">Salvar</span></button>
					<button type="button" class="btn btn-outline btn-sm" id="csvBtn"><?php echo Icons::get( 'file-spreadsheet', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="b_csv">CSV</span></button>
					<button type="button" class="btn btn-outline btn-sm" id="printBtn"><?php echo Icons::get( 'printer', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="b_print">Imprimir / PDF</span></button>
					<button type="button" class="btn btn-outline btn-sm" id="shareBtn"><?php echo Icons::get( 'link-2', 'icon-sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <span data-i18n="b_copyLink">Copiar link</span></button>
				</div>
				<h3 class="label mb-2" id="htrb-saved-h" data-i18n="r_savedEntries">Entradas salvas</h3>
				<div class="saved-list" id="savedList"><span class="body-sm" data-i18n="r_nothingSaved">Nada salvo ainda.</span></div>
			</section>
		</div>

		<!-- FAQ -->
		<details class="card card-pad faq">
			<summary class="heading-md">
				<?php echo Icons::get( 'info', 'icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span data-i18n="faq_title">Como funciona</span>
				<?php echo Icons::get( 'chevron-down', 'icon chev' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</summary>
			<div class="mt-2">
				<p data-i18n-html="faq1"><?php echo wp_kses_post( __( '<strong>Horas</strong> = (Fim − Início) − Intervalo, por dia, somadas no período. Turnos noturnos são tratados automaticamente.', 'horas-trabalhadas' ) ); ?></p>
				<p data-i18n-html="faq2"><?php echo wp_kses_post( __( '<strong>Horas extras</strong> suportam semanal (>40h), um limite diário personalizado e regras reais dos EUA (Califórnia 8h/dia + dobro após 12h, Alasca, Nevada).', 'horas-trabalhadas' ) ); ?></p>
				<p data-i18n-html="faq3"><?php echo wp_kses_post( __( '<strong>Pagamento</strong> = normais × valor + extras × valor × multiplicador (+ dobro) + gorjetas. <strong>Líquido</strong> aplica seus impostos / descontos %.', 'horas-trabalhadas' ) ); ?></p>
				<p data-i18n-html="faq4"><?php echo wp_kses_post( __( '<strong>Tudo funciona offline</strong> — sem login, os dados ficam no seu navegador. Salve vários funcionários, exporte CSV, imprima em PDF ou copie um link compartilhável.', 'horas-trabalhadas' ) ); ?></p>
			</div>
		</details>
	</div>

	<div class="toast" id="toast" role="status" aria-live="polite"></div>
</div>
