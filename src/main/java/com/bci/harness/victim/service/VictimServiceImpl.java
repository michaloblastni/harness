package com.bci.harness.victim.service;

import com.bci.harness.victim.entity.Victim;
import com.bci.harness.victim.repository.VictimRepository;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.transaction.Transactional;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.GrantedAuthority;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.core.userdetails.User;
import org.springframework.security.core.userdetails.UserDetails;
import org.springframework.security.core.userdetails.UserDetailsService;
import org.springframework.security.core.userdetails.UsernameNotFoundException;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.web.context.request.RequestContextHolder;
import org.springframework.web.context.request.ServletRequestAttributes;

import java.util.HashSet;
import java.util.Set;

@Service
public class VictimServiceImpl implements VictimService {
    private AuthenticationManager authenticationManager;

    private VictimRepository victimRepository;

    private UserDetailsService userDetailsService;

    private BCryptPasswordEncoder bCryptPasswordEncoder = new BCryptPasswordEncoder();

    private static final Logger logger = LoggerFactory.getLogger(VictimService.class);

    @Autowired
    public VictimServiceImpl(VictimRepository victimRepository,
                             AuthenticationManager authentication,
                             UserDetailsService userDetailsService) {
        this.authenticationManager = authentication;
        this.victimRepository = victimRepository;
        this.userDetailsService = userDetailsService;
    }

    @Override
    public void save(Victim user) {
        user.setPassword(bCryptPasswordEncoder.encode(user.getPassword()));
        victimRepository.save(user);
    }

    @Override
    public Victim findByUsername(String username) {
        return victimRepository.findOneByUsername(username);
    }

    @Override
    public String findLoggedInUsername() {
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth.getPrincipal() instanceof String && auth.getPrincipal().equals("anonymousUser"))
            return (String) auth.getPrincipal();
        else if (auth instanceof User)
            return ((User) auth.getPrincipal()).getUsername();
        else if (auth instanceof UsernamePasswordAuthenticationToken) {
            return auth.getName();
        } else
            throw new UnsupportedOperationException();
    }

    @Override
    public UserDetails getUserDetails() {
        Object userDetails = SecurityContextHolder.getContext().getAuthentication().getDetails();
        if (userDetails instanceof UserDetails) {
            return (UserDetails) userDetails;
        }

        return null;
    }

    @Override
    public void autologin(String username, String password) {
        UserDetails userDetails = userDetailsService.loadUserByUsername(username);
        UsernamePasswordAuthenticationToken usernamePasswordAuthenticationToken = new UsernamePasswordAuthenticationToken(userDetails, password, userDetails.getAuthorities());

        HttpServletRequest request = ((ServletRequestAttributes) RequestContextHolder.currentRequestAttributes())
                .getRequest();

        authenticationManager.authenticate(usernamePasswordAuthenticationToken);

        if (usernamePasswordAuthenticationToken.isAuthenticated()) {
            SecurityContextHolder.getContext().setAuthentication(usernamePasswordAuthenticationToken);
            logger.debug(String.format("Auto login %s successfully!", username));
        }
    }

    @Override
    public Victim getCurrentUser() {
        String username = findLoggedInUsername();
        if (username == null) {
            throw new IllegalStateException("Logged in user couldn't be found: username is null!");
        }

        if (username.equals("anonymousUser"))
            throw new IllegalStateException("Anonymous users cannot access this!");

        Victim user = findByUsername(username);
        if (user == null) {
            throw new IllegalStateException("Logged in user couldn't be found: user is null!");
        }

        return user;
    }

    @Override
    public long getVictimCount() {
        return victimRepository.count();
    }
}
