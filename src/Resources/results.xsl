<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" id="stylesheet">

    <xsl:output method="xml" doctype-system="about:legacy-compat" indent="yes" encoding="UTF-8"/>

    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta charset="utf-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1"/>
                <title>Results | Steward</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5/dist/css/bootstrap.min.css" rel="stylesheet"/>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1/font/bootstrap-icons.min.css" rel="stylesheet"/>
            </head>
            <body>

                <xsl:variable name="in-progress" select="count(//testcase) &gt; count(//testcase[@status='done'])" />

                <div class="container">
                    <div class="pb-0 mt-4 mb-4 border-bottom d-flex align-items-center">
                        <h1>Steward results</h1>
                        <xsl:if test="$in-progress">
                            <div class="spinner-border ms-auto" role="status" aria-hidden="true" title="Test are still in progress"></div>
                        </xsl:if>
                    </div>

                    <div class="row mb-4">
                        <div class="col-6">
                            <div class="card">
                                <div class="card-header">
                                    <h1>
                                        <xsl:value-of select="count(//testcase)"/>
                                        <xsl:choose>
                                            <xsl:when test="count(//testcase) = 1">
                                                testcase
                                            </xsl:when>
                                            <xsl:otherwise>
                                                testcases
                                            </xsl:otherwise>
                                        </xsl:choose>
                                    </h1>

                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">prepared: <xsl:value-of select="count(//testcase[@status='prepared'])"/></li>
                                    <li class="list-group-item">queued: <xsl:value-of select="count(//testcase[@status='queued'])"/></li>
                                    <li class="list-group-item">
                                        done: <xsl:value-of select="count(//testcase[@status='done'])"/>

                                        <ul style="list-style-type: none; padding-left: 20px;">
                                            <li>
                                                <i class="bi bi-check-lg"></i>
                                                passed: <xsl:value-of select="count(//testcase[@status='done' and @result='passed'])"/>
                                            </li>
                                            <li>
                                                <i class="bi bi-x-circle-fill"></i>
                                                failed: <xsl:value-of select="count(//testcase[@status='done' and @result='failed'])"/>
                                            </li>
                                            <li>
                                                <i class="bi bi-exclamation-triangle"></i>
                                                fatal: <xsl:value-of select="count(//testcase[@status='done' and @result='fatal'])"/>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card">
                                <div class="card-header">
                                    <h1>
                                        <xsl:value-of select="count(//test)"/>
                                        <xsl:choose>
                                            <xsl:when test="count(//test) = 1">
                                                test
                                            </xsl:when>
                                            <xsl:otherwise>
                                                tests
                                            </xsl:otherwise>
                                        </xsl:choose>
                                        <xsl:if test="$in-progress">
                                            <small class="text-body-secondary" style="font-size: 0.5em">(initialized so far)</small>
                                        </xsl:if>
                                    </h1>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item">started: <xsl:value-of select="count(//test[@status='started'])"/></li>
                                    <li class="list-group-item">
                                        done: <xsl:value-of select="count(//test[@status='done'])"/>

                                        <ul style="list-style-type: none; padding-left: 20px;">
                                            <li>
                                                <i class="bi bi-check-lg"></i>
                                                passed: <xsl:value-of select="count(//test[@status='done' and @result='passed'])"/>
                                            </li>
                                            <li>
                                                <i class="bi bi-x-circle-fill"></i>
                                                failed or broken: <xsl:value-of select="count(//test[@status='done' and (@result='failed' or @result='broken')])"/>
                                            </li>
                                            <li>
                                                <i class="bi bi-question-circle"></i>
                                                skipped or incomplete: <xsl:value-of select="count(//test[@status='done' and (@result='skipped' or @result='incomplete')])"/>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <xsl:variable name="testcase-progress-passed" select="round(100 div count(//testcase) * count(//testcase[@status='done' and @result='passed']))" />
                    <xsl:variable name="testcase-progress-failed-and-fatal" select="round(100 div count(//testcase) * count(//testcase[@status='done' and (@result='failed' or @result='fatal')]))" />

                    <div class="progress mb-4" style="height: 2em">
                        <div style="width: {$testcase-progress-failed-and-fatal}%">
                            <xsl:attribute name="class">
                                <xsl:text>progress-bar bg-danger</xsl:text>
                                <xsl:if test="$in-progress"> progress-bar-striped progress-bar-animated</xsl:if>
                            </xsl:attribute><xsl:value-of select="$testcase-progress-failed-and-fatal"/> %
                        </div>
                        <div style="width: {$testcase-progress-passed}%">
                            <xsl:attribute name="class">
                                <xsl:text>progress-bar bg-success</xsl:text>
                                <xsl:if test="$in-progress"> progress-bar-striped progress-bar-animated</xsl:if>
                            </xsl:attribute>
                            <xsl:if test="count(//testcase[@status='done']) &lt; 1">
                                <xsl:attribute name="aria-valuenow">0</xsl:attribute>
                            </xsl:if>
                            <xsl:value-of select="$testcase-progress-passed"/> %
                        </div>
                    </div>

                    <table class="table table-sm table-hover results-table">
                        <thead>
                            <tr>
                                <th colspan="2">Testcase / tests</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Duration</th>
                            </tr>
                        </thead>
                        <tbody class="table-group-divider">
                            <xsl:for-each select="//testcases/testcase">
                                <tr class="testcase-row table-active">
                                    <td colspan="2" style="word-break: break-all;" class="w-50">
                                        <xsl:value-of select="@name"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="@status"/>
                                    </td>
                                    <td>
                                        <xsl:attribute name="class">
                                            <xsl:choose>
                                                <xsl:when test="@result = 'passed'">bg-success-subtle</xsl:when>
                                                <xsl:when test="@result = 'failed'">bg-danger-subtle</xsl:when>
                                                <xsl:when test="@result = 'fatal'">bg-warning-subtle</xsl:when>
                                            </xsl:choose>
                                        </xsl:attribute>

                                        <!-- if the status is queued (this result is not yet know) only add time icon -->
                                        <xsl:if test="@status = 'queued'">
                                            <i class="bi bi-clock"></i>
                                        </xsl:if>

                                        <i>
                                            <xsl:attribute name="class">
                                                bi
                                                <xsl:choose>
                                                    <xsl:when test="@result = 'passed'">bi-check-lg</xsl:when>
                                                    <xsl:when test="@result = 'failed'">bi-x-circle-fill</xsl:when>
                                                    <xsl:when test="@result = 'fatal'">bi-exclamation-triangle</xsl:when>
                                                </xsl:choose>
                                            </xsl:attribute>
                                        </i>
                                        <xsl:text>&#160;&#160;</xsl:text>
                                        <xsl:value-of select="@result"/>
                                    </td>
                                    <td class="date date-start">
                                        <xsl:value-of select="@start"/>
                                    </td>
                                    <td class="date date-end">
                                        <xsl:value-of select="@end"/>
                                    </td>
                                    <td class="duration">
                                    </td>
                                </tr>
                                <xsl:if test="test">
                                    <xsl:for-each select="test">
                                        <tr class="test-row">
                                            <td></td>
                                            <td style="word-break: break-all;">
                                                <xsl:attribute name="title">
                                                    <xsl:value-of select="../@name"/>::<xsl:value-of select="@name"/>
                                                </xsl:attribute>
                                                <xsl:value-of select="@name"/>
                                            </td>
                                            <td>
                                                <!-- If the parent testcase ended with "fatal" and this one is still in "started" status, that
                                                this must be the one that "fatal"-ed, so we won't print its confusing "started" status -->
                                                <xsl:if test="not(@status = 'started' and ../@result = 'fatal')">
                                                    <xsl:value-of select="@status"/>
                                                </xsl:if>
                                            </td>
                                            <td>
                                                <xsl:choose>
                                                    <xsl:when test="@status = 'started' and ../@result = 'fatal'">
                                                        <xsl:attribute name="class">warning</xsl:attribute>
                                                        <i class="bi bi-exclamation-triangle"></i>&#160;&#160;fatal
                                                    </xsl:when>
                                                    <xsl:otherwise>
                                                        <xsl:attribute name="class">
                                                            <xsl:choose>
                                                                <xsl:when test="@result = 'passed'">bg-success-subtle</xsl:when>
                                                                <xsl:when test="@result = 'failed' or @result = 'broken'">bg-danger-subtle</xsl:when>
                                                                <xsl:when test="@result = 'skipped' or @result = 'incomplete'">bg-info-subtle</xsl:when>
                                                            </xsl:choose>
                                                        </xsl:attribute>
                                                        <i>
                                                            <xsl:attribute name="class">
                                                                bi
                                                                <xsl:choose>
                                                                    <xsl:when test="@result = 'passed'">bi-check-lg</xsl:when>
                                                                    <xsl:when test="@result = 'failed' or @result = 'broken'">bi-x-circle-fill</xsl:when>
                                                                    <xsl:when test="@result = 'skipped' or @result = 'incomplete'">bi-question-circle</xsl:when>
                                                                </xsl:choose>
                                                            </xsl:attribute>
                                                        </i>
                                                        <xsl:text>&#160;&#160;</xsl:text>
                                                        <xsl:value-of select="@result"/>
                                                    </xsl:otherwise>
                                                </xsl:choose>
                                            </td>
                                            <td class="date date-start">
                                                <xsl:value-of select="@start"/>
                                            </td>
                                            <td class="date date-end">
                                                <xsl:choose>
                                                    <xsl:when test="@status = 'started' and ../@result = 'fatal'">-</xsl:when>
                                                    <xsl:otherwise><xsl:value-of select="@end"/></xsl:otherwise>
                                                </xsl:choose>
                                            </td>
                                            <td class="duration">
                                            </td>
                                        </tr>
                                    </xsl:for-each>
                                </xsl:if>
                            </xsl:for-each>
                        </tbody>
                    </table>
                </div>
            <script src="https://cdn.jsdelivr.net/npm/jquery@3/dist/jquery.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/moment@2/moment.min.js"></script>
            <script>
                <![CDATA[
                $(function () {
                    // Ensure the script was not yet initialized (see Firefox bug #380828)
                    if (typeof window.wasInitialized != 'undefined') {
                        return;
                    }
                    window.wasInitialized = true;

                    // calculate and print test duration
                    $('table tr.test-row, table tr.testcase-row').each(function() {
                        var startDate = moment($('td.date-start', this).text());
                        var endValue = $('td.date-end', this).text();
                        var endDate = moment(endValue);
                        var isPending = false;
                        if (startDate.isValid() && endValue != '-') { // do not calculate when test fatal-ed
                            if (!endDate.isValid()) { // still running, add current time
                                isPending = true;
                                endDate = moment();
                            }

                            $('td.duration', this).html(
                                (isPending ? '<i>' : '') +
                                endDate.diff(startDate, 'seconds') + ' sec' +
                                (isPending ? '</i>' : '')
                            );
                        }
                    });

                    // convert ISO-8601 dates to more readable ones
                    $("td.date").each(function () {
                        if ($(this).text().length && $(this).text() != '-') {
                            $(this).text(moment($(this).text()).format('YYYY-MM-DD H:mm:ss'));
                        }
                    });
                });
                ]]>
            </script>
            </body>
        </html>
    </xsl:template>

    <xsl:template match="xsl:stylesheet">
        <!-- ignore the stylesheet from being processed -->
    </xsl:template>

</xsl:stylesheet>
