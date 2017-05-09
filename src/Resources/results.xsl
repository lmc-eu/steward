<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" id="stylesheet">

    <xsl:output method="xml" doctype-system="about:legacy-compat" indent="yes" encoding="UTF-8"/>

    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <meta charset="utf-8" />
                <title>Results | Steward</title>
                <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet"/>
            </head>
            <body>
                <div class="container">
                    <div class="page-header">
                        <h1>
                            Steward results
                            <span class="pull-right"><small>generated with ♥</small></span>
                        </h1>
                    </div>

                    <div class="row">
                        <div class="col-xs-6">
                            <div class="panel panel-default">
                                <div class="panel-heading"><h1><xsl:value-of select="count(//testcase)"/> testcases</h1></div>
                                <ul class="list-group">
                                    <li class="list-group-item">prepared: <xsl:value-of select="count(//testcase[@status='prepared'])"/></li>
                                    <li class="list-group-item">queued: <xsl:value-of select="count(//testcase[@status='queued'])"/></li>
                                    <li class="list-group-item">
                                        done: <xsl:value-of select="count(//testcase[@status='done'])"/>
                                        <ul style="list-style-type: none; padding-left: 20px;">
                                            <li>
                                                <span class="glyphicon glyphicon-ok"></span>
                                                passed: <xsl:value-of select="count(//testcase[@status='done' and @result='passed'])"/>
                                            </li>
                                            <li>
                                                <span class="glyphicon glyphicon-remove"></span>
                                                failed: <xsl:value-of select="count(//testcase[@status='done' and @result='failed'])"/>
                                            </li>
                                            <li>
                                                <span class="glyphicon glyphicon-warning-sign"></span>
                                                fatal: <xsl:value-of select="count(//testcase[@status='done' and @result='fatal'])"/>
                                            </li>
                                        </ul>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h1>
                                        <abbr title="Initialized so far"><xsl:value-of select="count(//test)"/></abbr>
                                        tests
                                    </h1>
                                </div>
                                <ul class="list-group">
                                    <li class="list-group-item">started: <xsl:value-of select="count(//test[@status='started'])"/></li>
                                    <li class="list-group-item">
                                        done: <xsl:value-of select="count(//test[@status='done'])"/>
                                        <ul style="list-style-type: none; padding-left: 20px;">
                                            <li>
                                                <span class="glyphicon glyphicon-ok"></span>
                                                passed: <xsl:value-of select="count(//test[@status='done' and @result='passed'])"/>
                                            </li>
                                            <li>
                                                <span class="glyphicon glyphicon-remove"></span>
                                                failed or broken: <xsl:value-of select="count(//test[@status='done' and (@result='failed' or @result='broken')])"/>
                                            </li>
                                            <li>
                                                <span class="glyphicon glyphicon-question-sign"></span>
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

                    <div class="progress">
                        <div class="progress-bar progress-bar-danger" style="width: {$testcase-progress-failed-and-fatal}%">
                            <xsl:value-of select="$testcase-progress-failed-and-fatal"/> %
                        </div>
                        <div style="width: {$testcase-progress-passed}%">
                            <xsl:attribute name="class">
                                progress-bar progress-bar-success
                                <xsl:if test="count(//testcase) &gt; count(//testcase[@status='done'])">progress-bar-striped active</xsl:if>
                            </xsl:attribute>
                            <xsl:if test="count(//testcase[@status='done']) &lt; 1">
                                <xsl:attribute name="aria-valuenow">0</xsl:attribute>
                            </xsl:if>
                            <xsl:value-of select="$testcase-progress-passed"/> %
                        </div>
                    </div>

                    <table class="table table-condensed table-hover results-table">
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
                        <tbody>
                            <xsl:for-each select="//testcases/testcase">
                                <tr class="testcase-row active">
                                    <td colspan="2" style="word-break: break-all;">
                                        <xsl:value-of select="@name"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="@status"/>
                                    </td>
                                    <td>
                                        <xsl:attribute name="class">
                                            <xsl:choose>
                                                <xsl:when test="@result = 'passed'">success</xsl:when>
                                                <xsl:when test="@result = 'failed'">danger</xsl:when>
                                                <xsl:when test="@result = 'fatal'">warning</xsl:when>
                                            </xsl:choose>
                                        </xsl:attribute>

                                        <!-- if the status is queued (this result is not yet know) only add time icon -->
                                        <xsl:if test="@status = 'queued'">
                                            <span class="glyphicon glyphicon-time"></span>
                                        </xsl:if>

                                        <span>
                                            <xsl:attribute name="class">
                                                glyphicon
                                                <xsl:choose>
                                                    <xsl:when test="@result = 'passed'">glyphicon-ok</xsl:when>
                                                    <xsl:when test="@result = 'failed'">glyphicon-remove</xsl:when>
                                                    <xsl:when test="@result = 'fatal'">glyphicon-warning-sign</xsl:when>
                                                </xsl:choose>
                                            </xsl:attribute>
                                        </span>
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
                                                        <span class="glyphicon glyphicon-warning-sign"></span>&#160;&#160;fatal
                                                    </xsl:when>
                                                    <xsl:otherwise>
                                                        <xsl:attribute name="class">
                                                            <xsl:choose>
                                                                <xsl:when test="@result = 'passed'">success</xsl:when>
                                                                <xsl:when test="@result = 'failed' or @result = 'broken'">danger</xsl:when>
                                                                <xsl:when test="@result = 'skipped' or @result = 'incomplete'">info</xsl:when>
                                                            </xsl:choose>
                                                        </xsl:attribute>
                                                        <span>
                                                            <xsl:attribute name="class">
                                                                glyphicon
                                                                <xsl:choose>
                                                                    <xsl:when test="@result = 'passed'">glyphicon-ok</xsl:when>
                                                                    <xsl:when test="@result = 'failed' or @result = 'broken'">glyphicon-remove</xsl:when>
                                                                    <xsl:when test="@result = 'skipped' or @result = 'incomplete'">glyphicon-question-sign</xsl:when>
                                                                </xsl:choose>
                                                            </xsl:attribute>
                                                        </span>
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
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.10.6/moment.min.js"></script>
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
