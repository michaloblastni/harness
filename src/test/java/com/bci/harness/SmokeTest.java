package com.bci.harness;

import com.bci.harness.victim.controller.VictimController;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;

import static org.assertj.core.api.Assertions.assertThat;

@SpringBootTest
class SmokeTest {
    @Autowired
    private VictimController victimController;

    @Test
    void contextLoads() throws Exception {
        assertThat(victimController).isNotNull();
    }
}
