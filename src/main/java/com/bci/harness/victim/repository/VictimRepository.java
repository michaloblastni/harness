package com.bci.harness.victim.repository;

import com.bci.harness.victim.entity.Victim;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

public interface VictimRepository extends JpaRepository<Victim, Long> {
    Victim findOneByUsername(String username);
}
