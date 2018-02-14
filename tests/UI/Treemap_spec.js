/*!
 * Piwik - free/libre analytics platform
 *
 * Screenshot test for TasksTimetable main page.
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

describe("Treemap", function () {
    this.timeout(0);

    var generalParams = 'idSite=1&period=year&date=2012-08-09',
        urlBase = 'module=CoreHome&action=index&token_auth=9ad1de7f8b329ab919d854c556f860c1&' + generalParams,
        normalUrl = "?module=Widgetize&action=iframe&moduleToWidgetize=DevicesDetection&idSite=1&period=year&date=2012-08-09&"
                  + "actionToWidgetize=getBrowsers&viewDataTable=table&filter_limit=5&isFooterExpandedInDashboard=1",
        actionsUrl = "?" + urlBase + "#?" + generalParams + "&category=General_Actions&subcategory=General_Pages"
        ;

    it('should load a normal report w/ the treemap visualization correctly', function (done) {
        expect.screenshot('normal_treemap').to.be.capture('.pageWrap', function (page) {
            page.load(normalUrl);
            page.evaluate(function () {
                $('.tableIcon[data-footer-icon-id=infoviz-treemap]').click();
            });
            page.wait(2000);
        }, done);
    });

    it('should load a report directly as treemap visualization correctly', function (done) {
        expect.screenshot('initial_treemap').to.be.capture('.pageWrap', function (page) {
            page.load(normalUrl + "&viewDataTable=infoviz-treemap");
            page.wait(1000);
        }, done);
    });

    it('should load an actions report on the actions page w/ the treemap visualization correctly', function (done){
        expect.screenshot('actions_treemap').to.be.captureSelector('.pageWrap', function (page) {
            page.load(actionsUrl);
            page.evaluate(function () {
                $('.tableIcon[data-footer-icon-id=infoviz-treemap]').click();
            });
            page.wait(2000);
        }, done);
    });
});