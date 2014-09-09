<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="xml" doctype-system="about:legacy-compat" indent="yes" encoding="UTF-8"/>

    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css" rel="stylesheet"/>
                <title>Steward results</title>
            </head>
            <body>
                <div class="container">
                    <div class="page-header">
                        <h1>Steward results
                            <div class="pull-right">
                                <small>generated with ♥</small>
                            </div>
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
                                        <ul>
                                            <li>passed: <xsl:value-of select="count(//testcase[@status='done' and @result='passed'])"/></li>
                                            <li>failed: <xsl:value-of select="count(//testcase[@status='done' and @result='failed'])"/></li>
                                            <li>fatal: <xsl:value-of select="count(//testcase[@status='done' and @result='fatal'])"/></li>
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
                                        <ul>
                                            <li>passed: <xsl:value-of select="count(//test[@status='done' and @result='passed'])"/></li>
                                            <li>failed or broken: <xsl:value-of select="count(//test[@status='done' and (@result='failed' or @result='broken')])"/></li>
                                            <li>skipped or incomplete: <xsl:value-of select="count(//test[@status='done' and (@result='skipped' or @result='incomplete')])"/></li>
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

                    <table class="table table-condensed table-striped table-hover">
                        <thead>
                            <tr>
                                <th colspan="2">Testcase / tests</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Start</th>
                                <th>End</th>
                            </tr>
                        </thead>
                        <tbody>
                            <xsl:for-each select="/testcases/testcase">
                                <tr>
                                    <td colspan="2">
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
                                        <xsl:value-of select="@result"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="@start"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="@end"/>
                                    </td>
                                </tr>
                                <xsl:if test="test">
                                    <xsl:for-each select="test">
                                        <tr>
                                            <td></td>
                                            <td>
                                                <xsl:value-of select="@name"/>
                                            </td>
                                            <td>
                                                <xsl:value-of select="@status"/>
                                            </td>
                                            <td>
                                                <xsl:attribute name="class">
                                                    <xsl:choose>
                                                        <xsl:when test="@result = 'passed'">success</xsl:when>
                                                        <xsl:when test="@result = 'failed' or @result = 'broken'">danger</xsl:when>
                                                        <xsl:when test="@result = 'skipped' or @result = 'incomplete'">info</xsl:when>
                                                    </xsl:choose>
                                                </xsl:attribute>
                                                <xsl:value-of select="@result"/>
                                            </td>
                                            <td>
                                                <xsl:value-of select="@start"/>
                                            </td>
                                            <td>
                                                <xsl:value-of select="@end"/>
                                            </td>
                                        </tr>
                                    </xsl:for-each>
                                </xsl:if>
                            </xsl:for-each>
                        </tbody>
                    </table>
                </div>
            </body>
        </html>
    </xsl:template>

</xsl:stylesheet>
