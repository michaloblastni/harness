package com.bci.harness.victim.service;

import com.bci.harness.victim.entity.Victim;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UsernameNotFoundException;

public interface VictimService {
    void save(Victim user);

    Victim findByUsername(String username);

    String findLoggedInUsername();

    UserDetails getUserDetails();

    void autologin(String username, String password);

    Victim getCurrentUser();

    long getVictimCount();
}