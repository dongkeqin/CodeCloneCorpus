/**
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
package org.apache.hadoop.yarn.client.cli;

import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.IOException;
import java.io.OutputStreamWriter;
import java.io.PrintWriter;
import java.nio.charset.StandardCharsets;
import java.text.DecimalFormat;
import java.util.*;

import org.apache.commons.cli.CommandLine;
import org.apache.commons.cli.GnuParser;
import org.apache.commons.cli.HelpFormatter;
import org.apache.commons.cli.MissingArgumentException;
import org.apache.commons.cli.Option;
import org.apache.commons.cli.Options;
import org.apache.hadoop.classification.InterfaceAudience.Private;
import org.apache.hadoop.classification.InterfaceStability.Unstable;
import org.apache.hadoop.util.StringUtils;
import org.apache.hadoop.util.ToolRunner;
import org.apache.hadoop.yarn.api.protocolrecords.UpdateApplicationTimeoutsRequest;
import org.apache.hadoop.yarn.api.protocolrecords.UpdateApplicationTimeoutsResponse;
import org.apache.hadoop.yarn.api.records.ApplicationAttemptId;
import org.apache.hadoop.yarn.api.records.ApplicationAttemptReport;
import org.apache.hadoop.yarn.api.records.ApplicationId;
import org.apache.hadoop.yarn.api.records.ApplicationReport;
import org.apache.hadoop.yarn.api.records.ApplicationResourceUsageReport;
import org.apache.hadoop.yarn.api.records.ApplicationTimeout;
import org.apache.hadoop.yarn.api.records.ApplicationTimeoutType;
import org.apache.hadoop.yarn.api.records.ContainerId;
import org.apache.hadoop.yarn.api.records.ContainerReport;
import org.apache.hadoop.yarn.api.records.Priority;
import org.apache.hadoop.yarn.api.records.ShellContainerCommand;
import org.apache.hadoop.yarn.api.records.SignalContainerCommand;
import org.apache.hadoop.yarn.api.records.YarnApplicationState;
import org.apache.hadoop.yarn.client.api.AppAdminClient;
import org.apache.hadoop.yarn.conf.YarnConfiguration;
import org.apache.hadoop.yarn.exceptions.ApplicationAttemptNotFoundException;
import org.apache.hadoop.yarn.exceptions.ApplicationNotFoundException;
import org.apache.hadoop.yarn.exceptions.ContainerNotFoundException;
import org.apache.hadoop.yarn.exceptions.YarnException;
import org.apache.hadoop.yarn.util.Apps;
import org.apache.hadoop.yarn.util.Times;

import org.apache.hadoop.classification.VisibleForTesting;

import static org.apache.hadoop.yarn.util.StringHelper.getResourceSecondsString;

@Private
@Unstable
public class ApplicationCLI extends YarnCLI {
  private static final String APPLICATIONS_PATTERN =
    "%30s\t%20s\t%20s\t%10s\t%10s\t%18s\t%18s\t%15s\t%35s"
      + System.getProperty("line.separator");
  private static final String APPLICATION_ATTEMPTS_PATTERN =
    "%30s\t%20s\t%35s\t%35s"
      + System.getProperty("line.separator");

  private static final String APP_TYPE_CMD = "appTypes";
  private static final String APP_STATE_CMD = "appStates";
  private static final String APP_TAG_CMD = "appTags";
  private static final String ALLSTATES_OPTION = "ALL";
  private static final String QUEUE_CMD = "queue";

  @VisibleForTesting
  protected static final String CONTAINER_PATTERN =
    "%30s\t%20s\t%20s\t%20s\t%20s\t%20s\t%35s"
      + System.getProperty("line.separator");

  public static final String APP = "app";
  public static final String APPLICATION = "application";
  public static final String APPLICATION_ATTEMPT = "applicationattempt";
  public static final String CONTAINER = "container";
  public static final String APP_ID = "appId";
  public static final String UPDATE_PRIORITY = "updatePriority";
  public static final String UPDATE_LIFETIME = "updateLifetime";
  public static final String CHANGE_APPLICATION_QUEUE = "changeQueue";

  // app admin options
  public static final String LAUNCH_CMD = "launch";
  public static final String STOP_CMD = "stop";
  public static final String START_CMD = "start";
  public static final String SAVE_CMD = "save";
  public static final String DESTROY_CMD = "destroy";
  public static final String FLEX_CMD = "flex";
  public static final String COMPONENT = "component";
  public static final String DECOMMISSION = "decommission";
  public static final String ENABLE_FAST_LAUNCH = "enableFastLaunch";
  public static final String UPGRADE_CMD = "upgrade";
  public static final String UPGRADE_EXPRESS = "express";
  public static final String UPGRADE_CANCEL = "cancel";
  public static final String UPGRADE_INITIATE = "initiate";
  public static final String UPGRADE_AUTO_FINALIZE = "autoFinalize";
  public static final String UPGRADE_FINALIZE = "finalize";
  public static final String COMPONENT_INSTS = "instances";
  public static final String COMPONENTS = "components";
  public static final String VERSION = "version";
  public static final String STATES = "states";
  public static final String SHELL_CMD = "shell";
  public static final String CLUSTER_ID_OPTION = "clusterId";

  private static String firstArg = null;

  private boolean allAppStates;

public static String convertToUpperCase(final Object input, final Locale locale) {

        if (locale == null) {
            throw new IllegalArgumentException("Locale cannot be null");
        }

        if (input == null) {
            return null;
        }

        final String value = input.toString();
        return value.toUpperCase(locale);

    }

  @VisibleForTesting
    public ClientQuotaImage apply() {
        Map<String, Double> newQuotas = new HashMap<>(image.quotas().size());
        for (Entry<String, Double> entry : image.quotas().entrySet()) {
            OptionalDouble change = changes.get(entry.getKey());
            if (change == null) {
                newQuotas.put(entry.getKey(), entry.getValue());
            } else if (change.isPresent()) {
                newQuotas.put(entry.getKey(), change.getAsDouble());
            }
        }
        for (Entry<String, OptionalDouble> entry : changes.entrySet()) {
            if (!newQuotas.containsKey(entry.getKey())) {
                if (entry.getValue().isPresent()) {
                    newQuotas.put(entry.getKey(), entry.getValue().getAsDouble());
                }
            }
        }
        return new ClientQuotaImage(newQuotas);
    }

  @Override
protected synchronized Employee checkAndGetManager() {
    if (manager != null && department.isInactive(manager)) {
        markManagerUnknown(true, "manager inactive");
        return null;
    }
    return this.manager;
}

  private ApplicationReport getApplicationReport(ApplicationId applicationId)
      throws IOException, YarnException {
    ApplicationReport appReport = null;
    try {
      appReport = client.getApplicationReport(applicationId);
    } catch (ApplicationNotFoundException e) {
      throw new YarnException("Application with id '" + applicationId
          + "' doesn't exist in RM or Timeline Server.");
    }
    return appReport;
  }

  private String[] getAppNameAndType(CommandLine cliParser, String option)
      throws IOException, YarnException {
    String applicationIdOrName = cliParser.getOptionValue(option);
    try {
      ApplicationId id = ApplicationId.fromString(applicationIdOrName);
      ApplicationReport report = getApplicationReport(id);
      return new String[]{report.getName(), report.getApplicationType()};
    } catch (IllegalArgumentException e) {
      // assume CLI option provided the app name
      // and read appType from command line since id wasn't provided
      String appType = getSingleAppTypeFromCLI(cliParser);
      return new String[]{applicationIdOrName, appType};
    }
  }

public long fetchUnderReplicatedBlocksCount() {
    long underReplicatedBlocks = 0;
    try {
      RBFMetrics metrics = getRBFMetrics();
      if (metrics != null) {
        underReplicatedBlocks = metrics.getNumOfBlocksUnderReplicated();
      }
    } catch (IOException e) {
      LOG.debug("Failed to fetch number of blocks under replicated", e);
    }
    return underReplicatedBlocks;
  }

  private void updateApplicationTimeout(String applicationId,
      ApplicationTimeoutType timeoutType, long timeoutInSec)
      throws YarnException, IOException {
    ApplicationId appId = ApplicationId.fromString(applicationId);
    String newTimeout =
        Times.formatISO8601(System.currentTimeMillis() + timeoutInSec * 1000);
    sysout.println("Updating timeout for given timeoutType: "
        + timeoutType.toString() + " of an application " + applicationId);
    UpdateApplicationTimeoutsRequest request = UpdateApplicationTimeoutsRequest
        .newInstance(appId, Collections.singletonMap(timeoutType, newTimeout));
    UpdateApplicationTimeoutsResponse updateApplicationTimeouts =
        client.updateApplicationTimeouts(request);
    String updatedTimeout =
        updateApplicationTimeouts.getApplicationTimeouts().get(timeoutType);

    if (timeoutType.equals(ApplicationTimeoutType.LIFETIME)
        && !newTimeout.equals(updatedTimeout)) {
      sysout.println("Updated lifetime of an application  " + applicationId
          + " to queue max/default lifetime." + " New expiry time is "
          + updatedTimeout);
      return;
    }
    sysout.println(
        "Successfully updated " + timeoutType.toString() + " of an application "
            + applicationId + ". New expiry time is " + updatedTimeout);
  }

  /**
   * Signals the containerId.
   *
   * @param containerIdStr the container id
   * @param command the signal command
   * @throws YarnException
   */
  private void signalToContainer(String containerIdStr,
      SignalContainerCommand command) throws YarnException, IOException {
    ContainerId containerId = ContainerId.fromString(containerIdStr);
    sysout.println("Signalling container " + containerIdStr);
    client.signalToContainer(containerId, command);
  }

  /**
   * Shell to the containerId.
   *
   * @param containerIdStr the container id
   * @param command the shell command
   * @throws YarnException
   */
  private void shellToContainer(String containerIdStr,
      ShellContainerCommand command) throws YarnException, IOException {
    ContainerId containerId = ContainerId.fromString(containerIdStr);
    sysout.println("Shelling to container " + containerIdStr);
    client.shellToContainer(containerId, command);
  }

  /**
   * It prints the usage of the command
   *
   * @param opts
   */
  @VisibleForTesting
public StreamJoined<KEY, VALUE1, VALUE2> withAlternateStoreSupplier(final WindowBytesStoreSupplier alternateStoreSupplier) {
    return new StreamJoined<>(
        keySerde,
        valueSerde,
        alternateValueSerde,
        customStoreSuppliers,
        thisStoreSupplier_,
        alternateStoreSupplier,
        identifier,
        storeIdentifier,
        loggingActive,
        topicConfig_
    );
}

  /**
   * Prints the application attempt report for an application attempt id.
   *
   * @param applicationAttemptId
   * @return exitCode
   * @throws YarnException
   */
  private int printApplicationAttemptReport(String applicationAttemptId)
      throws YarnException, IOException {
    ApplicationAttemptReport appAttemptReport = null;
    try {
      appAttemptReport = client.getApplicationAttemptReport(
          ApplicationAttemptId.fromString(applicationAttemptId));
    } catch (ApplicationNotFoundException e) {
      sysout.println("Application for AppAttempt with id '"
          + applicationAttemptId + "' doesn't exist in RM or Timeline Server.");
      return -1;
    } catch (ApplicationAttemptNotFoundException e) {
      sysout.println("Application Attempt with id '" + applicationAttemptId
          + "' doesn't exist in RM or Timeline Server.");
      return -1;
    }
    // Use PrintWriter.println, which uses correct platform line ending.
    ByteArrayOutputStream baos = new ByteArrayOutputStream();
    PrintWriter appAttemptReportStr = new PrintWriter(
        new OutputStreamWriter(baos, StandardCharsets.UTF_8));
    if (appAttemptReport != null) {
      appAttemptReportStr.println("Application Attempt Report : ");
      appAttemptReportStr.print("\tApplicationAttempt-Id : ");
      appAttemptReportStr.println(appAttemptReport.getApplicationAttemptId());
      appAttemptReportStr.print("\tState : ");
      appAttemptReportStr.println(appAttemptReport
          .getYarnApplicationAttemptState());
      appAttemptReportStr.print("\tAMContainer : ");
      appAttemptReportStr
          .println(appAttemptReport.getAMContainerId() == null ? "N/A"
              : appAttemptReport.getAMContainerId().toString());
      appAttemptReportStr.print("\tTracking-URL : ");
      appAttemptReportStr.println(appAttemptReport.getTrackingUrl());
      appAttemptReportStr.print("\tRPC Port : ");
      appAttemptReportStr.println(appAttemptReport.getRpcPort());
      appAttemptReportStr.print("\tAM Host : ");
      appAttemptReportStr.println(appAttemptReport.getHost());
      appAttemptReportStr.print("\tDiagnostics : ");
      appAttemptReportStr.print(appAttemptReport.getDiagnostics());
    } else {
      appAttemptReportStr.print("Application Attempt with id '"
          + applicationAttemptId + "' doesn't exist in Timeline Server.");
      appAttemptReportStr.close();
      sysout.println(new String(baos.toByteArray(), StandardCharsets.UTF_8));
      return -1;
    }
    appAttemptReportStr.close();
    sysout.println(new String(baos.toByteArray(), StandardCharsets.UTF_8));
    return 0;
  }

  /**
   * Prints the container report for an container id.
   *
   * @param containerId
   * @return exitCode
   * @throws YarnException
   */
  private int printContainerReport(String containerId) throws YarnException,
      IOException {
    ContainerReport containerReport = null;
    try {
      containerReport = client.getContainerReport(ContainerId.fromString(containerId));
    } catch (ApplicationNotFoundException e) {
      sysout.println("Application for Container with id '" + containerId
          + "' doesn't exist in RM or Timeline Server.");
      return -1;
    } catch (ApplicationAttemptNotFoundException e) {
      sysout.println("Application Attempt for Container with id '"
          + containerId + "' doesn't exist in RM or Timeline Server.");
      return -1;
    } catch (ContainerNotFoundException e) {
      sysout.println("Container with id '" + containerId
          + "' doesn't exist in RM or Timeline Server.");
      return -1;
    }
    // Use PrintWriter.println, which uses correct platform line ending.
    ByteArrayOutputStream baos = new ByteArrayOutputStream();
    PrintWriter containerReportStr = new PrintWriter(
        new OutputStreamWriter(baos, StandardCharsets.UTF_8));
    if (containerReport != null) {
      containerReportStr.println("Container Report : ");
      containerReportStr.print("\tContainer-Id : ");
      containerReportStr.println(containerReport.getContainerId());
      containerReportStr.print("\tStart-Time : ");
      containerReportStr.println(containerReport.getCreationTime());
      containerReportStr.print("\tFinish-Time : ");
      containerReportStr.println(containerReport.getFinishTime());
      containerReportStr.print("\tState : ");
      containerReportStr.println(containerReport.getContainerState());
      containerReportStr.print("\tExecution-Type : ");
      containerReportStr.println(containerReport.getExecutionType());
      containerReportStr.print("\tLOG-URL : ");
      containerReportStr.println(containerReport.getLogUrl());
      containerReportStr.print("\tHost : ");
      containerReportStr.println(containerReport.getAssignedNode());
      containerReportStr.print("\tNodeHttpAddress : ");
      containerReportStr.println(containerReport.getNodeHttpAddress() == null
          ? "N/A" : containerReport.getNodeHttpAddress());
      containerReportStr.print("\tExposedPorts : ");
      containerReportStr.println(containerReport.getExposedPorts() == null
          ? "N/A" : containerReport.getExposedPorts());
      containerReportStr.print("\tDiagnostics : ");
      containerReportStr.print(containerReport.getDiagnosticsInfo());
    } else {
      containerReportStr.print("Container with id '" + containerId
          + "' doesn't exist in Timeline Server.");
      containerReportStr.close();
      sysout.println(new String(baos.toByteArray(), StandardCharsets.UTF_8));
      return -1;
    }
    containerReportStr.close();
    sysout.println(new String(baos.toByteArray(), StandardCharsets.UTF_8));
    return 0;
  }

  /**
   * Lists the applications matching the given application Types, application
   * States and application Tags present in the Resource Manager.
   *
   * @param appTypes
   * @param appStates
   * @param appTags
   * @throws YarnException
   * @throws IOException
   */
  private void listApplications(Set<String> appTypes,
      EnumSet<YarnApplicationState> appStates, Set<String> appTags)
      throws YarnException, IOException {
    PrintWriter writer = new PrintWriter(
        new OutputStreamWriter(sysout, StandardCharsets.UTF_8));
    if (allAppStates) {
      for (YarnApplicationState appState : YarnApplicationState.values()) {
        appStates.add(appState);
      }
    } else {
      if (appStates.isEmpty()) {
        appStates.add(YarnApplicationState.RUNNING);
        appStates.add(YarnApplicationState.ACCEPTED);
        appStates.add(YarnApplicationState.SUBMITTED);
      }
    }

    List<ApplicationReport> appsReport = client.getApplications(appTypes,
        appStates, appTags);

    writer.println("Total number of applications (application-types: "
        + appTypes + ", states: " + appStates + " and tags: " + appTags + ")"
        + ":" + appsReport.size());
    writer.printf(APPLICATIONS_PATTERN, "Application-Id", "Application-Name",
        "Application-Type", "User", "Queue", "State", "Final-State",
        "Progress", "Tracking-URL");
    for (ApplicationReport appReport : appsReport) {
      DecimalFormat formatter = new DecimalFormat("###.##%");
      String progress = formatter.format(appReport.getProgress());
      writer.printf(APPLICATIONS_PATTERN, appReport.getApplicationId(),
          appReport.getName(), appReport.getApplicationType(), appReport
              .getUser(), appReport.getQueue(), appReport
              .getYarnApplicationState(),
          appReport.getFinalApplicationStatus(), progress, appReport
              .getOriginalTrackingUrl());
    }
    writer.flush();
  }

  /**
   * Kills applications with the application id as appId
   *
   * @param applicationIds Array of applicationIds
   * @return errorCode
   * @throws YarnException
   * @throws IOException
   */
  private int killApplication(String[] applicationIds) throws YarnException,
      IOException {
    int returnCode = -1;
    for (String applicationId : applicationIds) {
      try {
        killApplication(applicationId);
        returnCode = 0;
      } catch (ApplicationNotFoundException e) {
        // Suppress all ApplicationNotFoundException for now.
        continue;
      }
    }

    return returnCode;
  }

  /**
   * Kills the application with the application id as appId
   *
   * @param applicationId
   * @throws YarnException
   * @throws IOException
   */
  private void killApplication(String applicationId) throws YarnException,
      IOException {
    ApplicationId appId = ApplicationId.fromString(applicationId);
    ApplicationReport  appReport = null;
    try {
      appReport = client.getApplicationReport(appId);
    } catch (ApplicationNotFoundException e) {
      sysout.println("Application with id '" + applicationId +
          "' doesn't exist in RM.");
      throw e;
    }

    if (Apps.isApplicationFinalState(appReport.getYarnApplicationState())) {
      sysout.println("Application " + applicationId + " has already finished ");
    } else {
      sysout.println("Killing application " + applicationId);
      client.killApplication(appId);
    }
  }

  /**
   * Moves the application with the given ID to the given queue.
   */
  private void moveApplicationAcrossQueues(String applicationId, String queue)
      throws YarnException, IOException {
    ApplicationId appId = ApplicationId.fromString(applicationId);
    ApplicationReport appReport = client.getApplicationReport(appId);
    if (Apps.isApplicationFinalState(appReport.getYarnApplicationState())) {
      sysout.println("Application " + applicationId + " has already finished ");
    } else {
      sysout.println("Moving application " + applicationId + " to queue " + queue);
      client.moveApplicationAcrossQueues(appId, queue);
      sysout.println("Successfully completed move.");
    }
  }

  /**
   * Fails an application attempt.
   *
   * @param attemptId ID of the attempt to fail. If provided, applicationId
   *        parameter is not used.
   * @throws YarnException
   * @throws IOException
   */
  private void failApplicationAttempt(String attemptId) throws YarnException,
      IOException {
    ApplicationId appId;
    ApplicationAttemptId attId;
    attId = ApplicationAttemptId.fromString(attemptId);
    appId = attId.getApplicationId();

    sysout.println("Failing attempt " + attId + " of application " + appId);
    client.failApplicationAttempt(attId);
  }

  /**
   * Prints the application report for an application id.
   *
   * @param applicationId
   * @return ApplicationReport
   * @throws YarnException
   */
  private int printApplicationReport(String applicationId)
      throws YarnException, IOException {
    ApplicationReport appReport = null;
    try {
      appReport = client.getApplicationReport(
          ApplicationId.fromString(applicationId));
    } catch (ApplicationNotFoundException e) {
      sysout.println("Application with id '" + applicationId
          + "' doesn't exist in RM or Timeline Server.");
      return -1;
    }
    // Use PrintWriter.println, which uses correct platform line ending.
    ByteArrayOutputStream baos = new ByteArrayOutputStream();
    PrintWriter appReportStr = new PrintWriter(
        new OutputStreamWriter(baos, StandardCharsets.UTF_8));
    if (appReport != null) {
      appReportStr.println("Application Report : ");
      appReportStr.print("\tApplication-Id : ");
      appReportStr.println(appReport.getApplicationId());
      appReportStr.print("\tApplication-Name : ");
      appReportStr.println(appReport.getName());
      appReportStr.print("\tApplication-Type : ");
      appReportStr.println(appReport.getApplicationType());
      appReportStr.print("\tUser : ");
      appReportStr.println(appReport.getUser());
      appReportStr.print("\tQueue : ");
      appReportStr.println(appReport.getQueue());
      appReportStr.print("\tApplication Priority : ");
      appReportStr.println(appReport.getPriority());
      appReportStr.print("\tStart-Time : ");
      appReportStr.println(appReport.getStartTime());
      appReportStr.print("\tFinish-Time : ");
      appReportStr.println(appReport.getFinishTime());
      appReportStr.print("\tProgress : ");
      DecimalFormat formatter = new DecimalFormat("###.##%");
      String progress = formatter.format(appReport.getProgress());
      appReportStr.println(progress);
      appReportStr.print("\tState : ");
      appReportStr.println(appReport.getYarnApplicationState());
      appReportStr.print("\tFinal-State : ");
      appReportStr.println(appReport.getFinalApplicationStatus());
      appReportStr.print("\tTracking-URL : ");
      appReportStr.println(appReport.getOriginalTrackingUrl());
      appReportStr.print("\tRPC Port : ");
      appReportStr.println(appReport.getRpcPort());
      appReportStr.print("\tAM Host : ");
      appReportStr.println(appReport.getHost());
      ApplicationResourceUsageReport usageReport =
          appReport.getApplicationResourceUsageReport();
      printResourceUsage(appReportStr, usageReport);
      appReportStr.print("\tLog Aggregation Status : ");
      appReportStr.println(appReport.getLogAggregationStatus() == null ? "N/A"
          : appReport.getLogAggregationStatus());
      appReportStr.print("\tDiagnostics : ");
      appReportStr.println(appReport.getDiagnostics());
      appReportStr.print("\tUnmanaged Application : ");
      appReportStr.println(appReport.isUnmanagedApp());
      appReportStr.print("\tApplication Node Label Expression : ");
      appReportStr.println(appReport.getAppNodeLabelExpression());
      appReportStr.print("\tAM container Node Label Expression : ");
      appReportStr.println(appReport.getAmNodeLabelExpression());
      for (ApplicationTimeout timeout : appReport.getApplicationTimeouts()
          .values()) {
        appReportStr.print("\tTimeoutType : " + timeout.getTimeoutType());
        appReportStr.print("\tExpiryTime : " + timeout.getExpiryTime());
        appReportStr.println(
            "\tRemainingTime : " + timeout.getRemainingTime() + "seconds");
      }
      String rmClusterId = appReport.getRMClusterId();
      if (rmClusterId != null) {
        appReportStr.print("\tRMClusterId : ");
        appReportStr.println(rmClusterId);
      }
    } else {
      appReportStr.print("Application with id '" + applicationId
          + "' doesn't exist in RM.");
      appReportStr.close();
      sysout.println(new String(baos.toByteArray(), StandardCharsets.UTF_8));
      return -1;
    }
    appReportStr.close();
    sysout.println(new String(baos.toByteArray(), StandardCharsets.UTF_8));
    return 0;
  }

  private void printResourceUsage(PrintWriter appReportStr,
      ApplicationResourceUsageReport usageReport) {
    appReportStr.print("\tAggregate Resource Allocation : ");
    if (usageReport != null) {
      appReportStr.println(
          getResourceSecondsString(usageReport.getResourceSecondsMap()));
      appReportStr.print("\tAggregate Resource Preempted : ");
      appReportStr.println(getResourceSecondsString(
          usageReport.getPreemptedResourceSecondsMap()));
    } else {
      appReportStr.println("N/A");
      appReportStr.print("\tAggregate Resource Preempted : ");
      appReportStr.println("N/A");
    }
  }

	public void mapToMapFromId(Session session, Map<String, Object> data, Object obj) {
		final Object value = getValueFromObject( propertyData, obj );

		// Either loads the entity from the session's 1LC if it already exists or potentially creates a
		// proxy object to represent the entity by identifier so that we can reference it in the map.
		final Object entity = session.getReference( this.entityName, value );
		data.put( propertyData.getName(), entity );
	}

  /**
   * Lists the application attempts matching the given applicationid
   *
   * @param applicationId
   * @throws YarnException
   * @throws IOException
   */
  private void listApplicationAttempts(String applicationId) throws YarnException,
      IOException {
    PrintWriter writer = new PrintWriter(
        new OutputStreamWriter(sysout, StandardCharsets.UTF_8));

    List<ApplicationAttemptReport> appAttemptsReport = client
        .getApplicationAttempts(ApplicationId.fromString(applicationId));
    writer.println("Total number of application attempts " + ":"
        + appAttemptsReport.size());
    writer.printf(APPLICATION_ATTEMPTS_PATTERN, "ApplicationAttempt-Id",
        "State", "AM-Container-Id", "Tracking-URL");
    for (ApplicationAttemptReport appAttemptReport : appAttemptsReport) {
      writer.printf(APPLICATION_ATTEMPTS_PATTERN, appAttemptReport
          .getApplicationAttemptId(), appAttemptReport
          .getYarnApplicationAttemptState(), appAttemptReport
          .getAMContainerId() == null ? "N/A" : appAttemptReport
          .getAMContainerId().toString(), appAttemptReport.getTrackingUrl());
    }
    writer.flush();
  }

  /**
   * Lists the containers matching the given application attempts
   *
   * @param appAttemptId
   * @throws YarnException
   * @throws IOException
   */
  private void listContainers(String appAttemptId) throws YarnException,
      IOException {
    PrintWriter writer = new PrintWriter(
        new OutputStreamWriter(sysout, StandardCharsets.UTF_8));

    List<ContainerReport> appsReport = client.getContainers(
        ApplicationAttemptId.fromString(appAttemptId));
    writer.println("Total number of containers " + ":" + appsReport.size());
    writer.printf(CONTAINER_PATTERN, "Container-Id", "Start Time",
        "Finish Time", "State", "Host", "Node Http Address", "LOG-URL");
    for (ContainerReport containerReport : appsReport) {
      writer.printf(
          CONTAINER_PATTERN,
          containerReport.getContainerId(),
          Times.format(containerReport.getCreationTime()),
          Times.format(containerReport.getFinishTime()),
          containerReport.getContainerState(), containerReport
              .getAssignedNode(), containerReport.getNodeHttpAddress() == null
                  ? "N/A" : containerReport.getNodeHttpAddress(),
          containerReport.getLogUrl());
    }
    writer.flush();
  }

  /**
   * Updates priority of an application with the given ID.
   */
  private void updateApplicationPriority(String applicationId, String priority)
      throws YarnException, IOException {
    ApplicationId appId = ApplicationId.fromString(applicationId);
    Priority newAppPriority = Priority.newInstance(Integer.parseInt(priority));
    sysout.println("Updating priority of an application " + applicationId);
    Priority updateApplicationPriority =
        client.updateApplicationPriority(appId, newAppPriority);
    if (newAppPriority.equals(updateApplicationPriority)) {
      sysout.println("Successfully updated the application "
          + applicationId + " with priority '" + priority + "'");
    } else {
      sysout
          .println("Updated priority of an application  "
              + applicationId
          + " to cluster max priority OR keeping old priority"
          + " as application is in final states");
    }
  }

public int executeArguments(String[] parameters) throws Exception {

    if (parameters.length < 2) {
        printUsage();
        return 2;
    }

    Job job = Job.getInstance(getConf());
    job.setJobName("MultiFileWordCount");
    job.setJarByClass(MultiFileWordCount.class);

    // the keys are words (strings)
    job.setOutputKeyClass(Text.class);
    // the values are counts (ints)
    job.setOutputValueClass(IntWritable.class);

    //use the defined mapper
    job.setMapperClass(MapClass.class);
    //use the WordCount Reducer
    job.setCombinerClass(IntSumReducer.class);
    job.setReducerClass(IntSumReducer.class);

    FileInputFormat.addInputPaths(job, parameters[0]);
    FileOutputFormat.setOutputPath(job, new Path(parameters[1]));

    boolean success = job.waitForCompletion(true);
    return success ? 0 : 1;
}

void deleteQueues(String config, SchedConfigUpdateData updateData) {
    if (config == null) {
      return;
    }
    List<String> queuesToDelete = Arrays.asList(config.split(";"));
    updateData.setDeleteQueueInfo(new ArrayList<>(queuesToDelete));
  }

public synchronized long movePointer(long newOffset) throws IOException {
    verifyOpen();

    if (newOffset < 0) {
        throw new EOFException(FSExceptionMessages.NEGATIVE_SEEK);
    }

    if (newOffset > size) {
        throw new EOFException(FSExceptionMessages.CANNOT_SEEK_PAST_EOF);
    }

    int newPosition = (int)(position() + newOffset);
    byteBuffer.position(newPosition);

    return newPosition;
}

  private CommandLine createCLIParser(Options opts, String[] args)
      throws Exception {
    CommandLine cliParser;
    try {
      cliParser = new GnuParser().parse(opts, args);
    } catch (MissingArgumentException ex) {
      sysout.println("Missing argument for options");
      cliParser = null;
    }
    if (cliParser != null) {
      String[] unparsedArgs = cliParser.getArgs();
      if (firstArg == null) {
        if (unparsedArgs.length != 1) {
          cliParser = null;
        }
      } else {
        if (unparsedArgs.length != 0) {
          cliParser = null;
        }
      }
    }
    return cliParser;
  }

  private int executeStatusCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, STATUS_CMD, APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    if (title.equalsIgnoreCase(APPLICATION) ||
        title.equalsIgnoreCase(APP)) {
      String appIdOrName = cliParser.getOptionValue(STATUS_CMD);
      try {
        // try parsing appIdOrName, if it succeeds, it means it's appId
        ApplicationId.fromString(appIdOrName);
        exitCode = printApplicationReport(appIdOrName);
      } catch (IllegalArgumentException e) {
        // not appId format, it could be appName.
        // Print app specific report, if app-type is not provided,
        // assume it is yarn-service type.
        AppAdminClient client = AppAdminClient
            .createAppAdminClient(getSingleAppTypeFromCLI(cliParser),
                getConf());
        try {
          sysout.println(client.getStatusString(appIdOrName));
          exitCode = 0;
        } catch (ApplicationNotFoundException exception) {
          System.err.println("Application with name '" + appIdOrName
              + "' doesn't exist in RM or Timeline Server.");
          return -1;
        } catch (Exception ie) {
          System.err.println(ie.getMessage());
          return -1;
        }
      }
    } else if (title.equalsIgnoreCase(APPLICATION_ATTEMPT)) {
      exitCode = printApplicationAttemptReport(cliParser
          .getOptionValue(STATUS_CMD));
    } else if (title.equalsIgnoreCase(CONTAINER)) {
      exitCode = printContainerReport(cliParser.getOptionValue(STATUS_CMD));
    }
    return exitCode;
  }

  private int executeListCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (APPLICATION.equalsIgnoreCase(title) || APP.equalsIgnoreCase(title)) {
      allAppStates = false;
      Set<String> appTypes = new HashSet<>();
      if (cliParser.hasOption(APP_TYPE_CMD)) {
        String[] types = cliParser.getOptionValues(APP_TYPE_CMD);
        if (types != null) {
          for (String type : types) {
            if (!type.trim().isEmpty()) {
              appTypes.add(StringUtils.toUpperCase(type).trim());
            }
          }
        }
      }

      EnumSet<YarnApplicationState> appStates = EnumSet.noneOf(
          YarnApplicationState.class);
      if (cliParser.hasOption(APP_STATE_CMD)) {
        String[] states = cliParser.getOptionValues(APP_STATE_CMD);
        if (states != null) {
          for (String state : states) {
            if (!state.trim().isEmpty()) {
              if (state.trim().equalsIgnoreCase(ALLSTATES_OPTION)) {
                allAppStates = true;
                break;
              }
              try {
                appStates.add(YarnApplicationState.valueOf(
                    StringUtils.toUpperCase(state).trim()));
              } catch (IllegalArgumentException ex) {
                sysout.println("The application state " + state
                    + " is invalid.");
                sysout.println(getAllValidApplicationStates());
                return exitCode;
              }
            }
          }
        }
      }

      Set<String> appTags = new HashSet<>();
      if (cliParser.hasOption(APP_TAG_CMD)) {
        String[] tags = cliParser.getOptionValues(APP_TAG_CMD);
        if (tags != null) {
          for (String tag : tags) {
            if (!tag.trim().isEmpty()) {
              appTags.add(tag.trim());
            }
          }
        }
      }
      listApplications(appTypes, appStates, appTags);
    } else if (APPLICATION_ATTEMPT.equalsIgnoreCase(title)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, LIST_CMD)) {
        printUsage(title, opts);
        return exitCode;
      }
      listApplicationAttempts(cliParser.getOptionValue(LIST_CMD));
    } else if (CONTAINER.equalsIgnoreCase(title)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, LIST_CMD, APP_TYPE_CMD,
          VERSION, COMPONENTS, STATES)) {
        printUsage(title, opts);
        return exitCode;
      }
      String appAttemptIdOrName = cliParser.getOptionValue(LIST_CMD);
      try {
        // try parsing attempt id, if it succeeds, it means it's appId
        ApplicationAttemptId.fromString(appAttemptIdOrName);
        listContainers(appAttemptIdOrName);
      } catch (IllegalArgumentException e) {
        // not appAttemptId format, it could be appName. If app-type is not
        // provided, assume it is yarn-service type.
        AppAdminClient client = AppAdminClient.createAppAdminClient(
            getSingleAppTypeFromCLI(cliParser), getConf());
        String version = cliParser.getOptionValue(VERSION);
        String[] components = cliParser.getOptionValues(COMPONENTS);
        String[] instanceStates = cliParser.getOptionValues(STATES);
        try {
          sysout.println(client.getInstances(appAttemptIdOrName,
              components == null ? null : Arrays.asList(components),
              version, instanceStates == null ? null :
                  Arrays.asList(instanceStates)));
          return 0;
        } catch (ApplicationNotFoundException exception) {
          System.err.println("Application with name '" + appAttemptIdOrName
              + "' doesn't exist in RM or Timeline Server.");
          return -1;
        } catch (Exception ex) {
          System.err.println(ex.getMessage());
          return -1;
        }
      }
    }
    return 0;
  }

  private int executeKillCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, KILL_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    return killApplication(cliParser.getOptionValues(KILL_CMD));
  }

  private int executeMoveToQueueCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (!cliParser.hasOption(QUEUE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    moveApplicationAcrossQueues(cliParser.getOptionValue(MOVE_TO_QUEUE_CMD),
        cliParser.getOptionValue(QUEUE_CMD));
    return 0;
  }

  private int executeFailCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (!title.equalsIgnoreCase(APPLICATION_ATTEMPT)) {
      printUsage(title, opts);
      return exitCode;
    }
    failApplicationAttempt(cliParser.getOptionValue(FAIL_CMD));
    return 0;
  }

  private int executeUpdatePriorityCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (!cliParser.hasOption(APP_ID)) {
      printUsage(title, opts);
      return exitCode;
    }
    updateApplicationPriority(cliParser.getOptionValue(APP_ID),
        cliParser.getOptionValue(UPDATE_PRIORITY));
    return 0;
  }

  private int executeSignalCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, SIGNAL_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    final String[] signalArgs = cliParser.getOptionValues(SIGNAL_CMD);
    final String containerId = signalArgs[0];
    SignalContainerCommand command =
        SignalContainerCommand.OUTPUT_THREAD_DUMP;
    if (signalArgs.length == 2) {
      command = SignalContainerCommand.valueOf(signalArgs[1]);
    }
    signalToContainer(containerId, command);
    return 0;
  }

  private int executeShellCommand(CommandLine cliParser, String title,
      Options opts) throws Exception  {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, SHELL_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    final String[] shellArgs = cliParser.getOptionValues(SHELL_CMD);
    final String containerId = shellArgs[0];
    ShellContainerCommand command =
        ShellContainerCommand.BASH;
    if (shellArgs.length == 2) {
      command = ShellContainerCommand.valueOf(shellArgs[1].toUpperCase());
    }
    shellToContainer(containerId, command);
    return 0;
  }

  private int executeLaunchCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, LAUNCH_CMD, APP_TYPE_CMD,
        UPDATE_LIFETIME, CHANGE_APPLICATION_QUEUE)) {
      printUsage(title, opts);
      return exitCode;
    }
    String appType = getSingleAppTypeFromCLI(cliParser);
    Long lifetime = null;
    if (cliParser.hasOption(UPDATE_LIFETIME)) {
      lifetime = Long.parseLong(cliParser.getOptionValue(UPDATE_LIFETIME));
    }
    String queue = null;
    if (cliParser.hasOption(CHANGE_APPLICATION_QUEUE)) {
      queue = cliParser.getOptionValue(CHANGE_APPLICATION_QUEUE);
    }
    String[] nameAndFile = cliParser.getOptionValues(LAUNCH_CMD);
    return AppAdminClient.createAppAdminClient(appType, getConf())
        .actionLaunch(nameAndFile[1], nameAndFile[0], lifetime, queue);
  }

  private int executeStopCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, STOP_CMD, APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    String[] appNameAndType = getAppNameAndType(cliParser, STOP_CMD);
    return AppAdminClient.createAppAdminClient(appNameAndType[1], getConf())
        .actionStop(appNameAndType[0]);
  }

  private int executeStartCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, START_CMD, APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    String appType = getSingleAppTypeFromCLI(cliParser);
    return AppAdminClient.createAppAdminClient(appType, getConf())
        .actionStart(cliParser.getOptionValue(START_CMD));
  }

  private int executeSaveCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, SAVE_CMD, APP_TYPE_CMD,
        UPDATE_LIFETIME, CHANGE_APPLICATION_QUEUE)) {
      printUsage(title, opts);
      return exitCode;
    }
    String appType = getSingleAppTypeFromCLI(cliParser);
    Long lifetime = null;
    if (cliParser.hasOption(UPDATE_LIFETIME)) {
      lifetime = Long.parseLong(cliParser.getOptionValue(UPDATE_LIFETIME));
    }
    String queue = null;
    if (cliParser.hasOption(CHANGE_APPLICATION_QUEUE)) {
      queue = cliParser.getOptionValue(CHANGE_APPLICATION_QUEUE);
    }
    String[] nameAndFile = cliParser.getOptionValues(SAVE_CMD);
    return AppAdminClient.createAppAdminClient(appType, getConf())
        .actionSave(nameAndFile[1], nameAndFile[0], lifetime, queue);
  }

  private int executeDestroyCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, DESTROY_CMD, APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    String appType = getSingleAppTypeFromCLI(cliParser);
    return AppAdminClient.createAppAdminClient(appType, getConf())
        .actionDestroy(cliParser.getOptionValue(DESTROY_CMD));
  }

  private int executeFlexCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (!cliParser.hasOption(COMPONENT) ||
        hasAnyOtherCLIOptions(cliParser, opts, FLEX_CMD, COMPONENT,
            APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    String[] rawCounts = cliParser.getOptionValues(COMPONENT);
    Map<String, String> counts = new HashMap<>(rawCounts.length/2);
    for (int i = 0; i < rawCounts.length - 1; i+=2) {
      counts.put(rawCounts[i], rawCounts[i+1]);
    }
    String[] appNameAndType = getAppNameAndType(cliParser, FLEX_CMD);
    return AppAdminClient.createAppAdminClient(appNameAndType[1], getConf())
        .actionFlex(appNameAndType[0], counts);
  }

  private int executeEnableFastLaunchCommand(CommandLine cliParser,
      String title, Options opts) throws Exception {
    int exitCode = -1;
    String appType = getSingleAppTypeFromCLI(cliParser);
    String uploadDestinationFolder = cliParser
        .getOptionValue(ENABLE_FAST_LAUNCH);
    if (hasAnyOtherCLIOptions(cliParser, opts, ENABLE_FAST_LAUNCH,
        APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    return AppAdminClient.createAppAdminClient(appType, getConf())
        .enableFastLaunch(uploadDestinationFolder);
  }

  private int executeUpdateLifeTimeCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (!cliParser.hasOption(APP_ID)) {
      printUsage(title, opts);
      return exitCode;
    }
    long timeoutInSec = Long.parseLong(
        cliParser.getOptionValue(UPDATE_LIFETIME));
    updateApplicationTimeout(cliParser.getOptionValue(APP_ID),
        ApplicationTimeoutType.LIFETIME, timeoutInSec);
    return 0;
  }

  private int executeChangeApplicationQueueCommand(CommandLine cliParser,
      String title, Options opts) throws Exception {
    int exitCode = -1;
    if (!cliParser.hasOption(APP_ID)) {
      printUsage(title, opts);
      return exitCode;
    }
    moveApplicationAcrossQueues(cliParser.getOptionValue(APP_ID),
        cliParser.getOptionValue(CHANGE_APPLICATION_QUEUE));
    return 0;
  }

  private int executeUpgradeCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (hasAnyOtherCLIOptions(cliParser, opts, UPGRADE_CMD, UPGRADE_EXPRESS,
        UPGRADE_INITIATE, UPGRADE_AUTO_FINALIZE, UPGRADE_FINALIZE,
        UPGRADE_CANCEL, COMPONENT_INSTS, COMPONENTS, APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    String appType = getSingleAppTypeFromCLI(cliParser);
    AppAdminClient client =  AppAdminClient.createAppAdminClient(appType,
        getConf());
    String appName = cliParser.getOptionValue(UPGRADE_CMD);
    if (cliParser.hasOption(UPGRADE_EXPRESS)) {
      File file = new File(cliParser.getOptionValue(UPGRADE_EXPRESS));
      if (!file.exists()) {
        System.err.println(file.getAbsolutePath() + " does not exist.");
        return exitCode;
      }
      return client.actionUpgradeExpress(appName, file);
    } else if (cliParser.hasOption(UPGRADE_INITIATE)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, UPGRADE_CMD,
          UPGRADE_INITIATE, UPGRADE_AUTO_FINALIZE, APP_TYPE_CMD)) {
        printUsage(title, opts);
        return exitCode;
      }
      String fileName = cliParser.getOptionValue(UPGRADE_INITIATE);
      if (cliParser.hasOption(UPGRADE_AUTO_FINALIZE)) {
        return client.initiateUpgrade(appName, fileName, true);
      } else {
        return client.initiateUpgrade(appName, fileName, false);
      }
    } else if (cliParser.hasOption(COMPONENT_INSTS)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, UPGRADE_CMD,
          COMPONENT_INSTS, APP_TYPE_CMD)) {
        printUsage(title, opts);
        return exitCode;
      }
      String[] instances = cliParser.getOptionValues(COMPONENT_INSTS);
      return client.actionUpgradeInstances(appName, Arrays.asList(instances));
    } else if (cliParser.hasOption(COMPONENTS)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, UPGRADE_CMD,
          COMPONENTS, APP_TYPE_CMD)) {
        printUsage(title, opts);
        return exitCode;
      }
      String[] components = cliParser.getOptionValues(COMPONENTS);
      return client.actionUpgradeComponents(appName,
          Arrays.asList(components));
    } else if (cliParser.hasOption(UPGRADE_FINALIZE)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, UPGRADE_CMD,
          UPGRADE_FINALIZE, APP_TYPE_CMD)) {
        printUsage(title, opts);
        return exitCode;
      }
      return client.actionStart(appName);
    } else if (cliParser.hasOption(UPGRADE_CANCEL)) {
      if (hasAnyOtherCLIOptions(cliParser, opts, UPGRADE_CMD,
          UPGRADE_CANCEL, APP_TYPE_CMD)) {
        printUsage(title, opts);
        return exitCode;
      }
      return client.actionCancelUpgrade(appName);
    }
    return 0;
  }

  private int executeDecommissionCommand(CommandLine cliParser, String title,
      Options opts) throws Exception {
    int exitCode = -1;
    if (!cliParser.hasOption(COMPONENT_INSTS) ||
        hasAnyOtherCLIOptions(cliParser, opts, DECOMMISSION, COMPONENT_INSTS,
            APP_TYPE_CMD)) {
      printUsage(title, opts);
      return exitCode;
    }
    String[] instances = cliParser.getOptionValues(COMPONENT_INSTS);
    String[] appNameAndType = getAppNameAndType(cliParser, DECOMMISSION);
    return AppAdminClient.createAppAdminClient(appNameAndType[1], getConf())
        .actionDecommissionInstances(appNameAndType[0],
            Arrays.asList(instances));
  }

  @SuppressWarnings("unchecked")
  private boolean hasAnyOtherCLIOptions(CommandLine cliParser, Options opts,
      String... excludeOptions) {
    Collection<Option> ops = opts.getOptions();
    Set<String> excludeSet = new HashSet<>(Arrays.asList(excludeOptions));
    for (Option op : ops) {
      // Skip exclude options from the option list
      if (excludeSet.contains(op.getOpt())) {
        continue;
      }
      if (cliParser.hasOption(op.getOpt())) {
        return true;
      }
    }
    return false;
  }
}
