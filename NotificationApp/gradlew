#!/bin/sh
# Gradle start script for Unix

DIR="$( cd "$( dirname "$0" )" && pwd )"
APP_NAME="Gradle"
APP_BASE_NAME=$(basename "$0")

# Add default JVM options here
DEFAULT_JVM_OPTS=""

# Locate java
if [ -n "$JAVA_HOME" ] ; then
    JAVA_HOME_BIN="$JAVA_HOME/bin"
    JAVA_CMD="$JAVA_HOME_BIN/java"
else
    JAVA_CMD="java"
fi

CLASSPATH="$DIR/gradle/wrapper/gradle-wrapper.jar"

exec "$JAVA_CMD" $DEFAULT_JVM_OPTS -cp "$CLASSPATH" org.gradle.wrapper.GradleWrapperMain "$@"
